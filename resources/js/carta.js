import { gameClient } from './gameClient';
import { renderIcons } from './icons';

const ROOM_KEY = 'carta-room-id';
const SUITS = {
    coins: { en: 'Coins', ar: 'ذهب' },
    swords: { en: 'Swords', ar: 'سيوف' },
    cups: { en: 'Cups', ar: 'طباق' },
    clubs: { en: 'Clubs', ar: 'زراوط' },
};
const ordinal = n => n + (['th', 'st', 'nd', 'rd'][(n % 100 - 20) % 10] || ['th', 'st', 'nd', 'rd'][n % 100] || 'th');
// Where opponents sit around the oval, in clockwise turn order starting at your left.
const SEATS = { 1: ['top'], 2: ['left', 'right'], 3: ['left', 'top', 'right'], 4: ['left', 'top-left', 'top-right', 'right'], 5: ['left', 'top-left', 'top', 'top-right', 'right'] };

// Display-only client: every card, turn and ranking shown here comes from the server's view of this seat.
export default () => ({
    suits: SUITS, screen: 'home', loading: true, busy: false, error: '',
    room: null, stopWatch: null, form: { name: '', capacity: 4, code: '', joinName: '' },
    suitPicker: null, toasts: [], seenMove: null, copied: '',

    get game() { return this.room?.game || null; },
    get me() { return this.game?.you || null; },
    get members() { return this.room?.members || []; },
    get everyoneReady() { return this.members.length >= 2 && this.members.every(member => member.ready); },
    get myMember() { return this.members[this.room?.viewerIndex]; },
    // Opponents in clockwise order starting after this seat, so the table reads like a real one.
    get opponents() {
        const players = this.game?.players || [], seat = this.me?.seat ?? 0, count = players.length;
        return Array.from({ length: Math.max(0, count - 1) }, (_, i) => players[(seat + 1 + i) % count]);
    },
    get currentName() { return this.game?.players?.find(player => player.seat === this.game.currentSeat)?.name || ''; },
    get turnText() {
        const game = this.game;
        if (!game || game.status !== 'PLAYING') return '';
        if (this.me?.status === 'FINISHED') return `You finished ${ordinal(this.me.rank)}. Watching ${this.currentName}.`;
        if (!this.me?.isTurn) return `${this.currentName} is playing…`;
        if (game.pending.effect === 'SKIP') return 'Skip! Answer with a 1, or accept losing your turn.';
        if (game.pending.effect === 'DRAW_TWO') return `+${game.pending.draw} for you! Answer with a 2, or draw them.`;
        if (this.me.canPass) return 'You drew a playable card. Play it or pass.';
        if (this.me.canDraw) return 'No playable card. Draw one from the pile.';
        return 'Your turn. Play a card of the same suit or number.';
    },
    pendingText() {
        const pending = this.game?.pending;
        if (!pending || pending.effect === 'NONE') return '';
        return pending.effect === 'SKIP' ? 'Skip pending' : `+${pending.draw} pending`;
    },
    suitName(suit) { return suit ? `${SUITS[suit].en} · ${SUITS[suit].ar}` : ''; },
    cardLabel(card) { return `${card.rank} of ${SUITS[card.suit].en}`; },
    ordinal,
    backs(count) { return Array.from({ length: Math.min(count, 7) }, (_, i) => i); },
    seatPosition(index) { return (SEATS[this.opponents.length] || [])[index] || 'top'; },
    initial(name) { return (name || '?').trim().charAt(0).toUpperCase(); },
    // Spreads cards like a held fan: rotation from the middle card, and a slight arc downward at the edges.
    fanStyle(index, count, maxStep) {
        const middle = (count - 1) / 2, step = Math.min(maxStep, 44 / Math.max(1, count - 1)), offset = index - middle;
        return `--rot:${(offset * step).toFixed(2)}deg;--lift:${(offset * offset * step * 0.18).toFixed(1)}px`;
    },
    get activeCount() { return (this.game?.players || []).filter(player => player.status === 'ACTIVE').length; },
    get statusTitle() {
        if (this.game?.status === 'FINISHED') return 'Match over';
        if (this.me?.status === 'FINISHED') return 'Spectating';
        return this.me?.isTurn ? 'Your turn' : 'Round in progress';
    },
    rankTag(player) {
        if (player.status === 'LOSER') return 'Loser';
        if (player.status === 'FINISHED') return player.rank === 1 ? 'Winner · 1st' : ordinal(player.rank);
        return player.isTurn ? 'Playing' : `${player.cards} card${player.cards === 1 ? '' : 's'}`;
    },

    async init() {
        const invite = new URLSearchParams(location.search).get('join');
        let saved = null; try { saved = sessionStorage.getItem(ROOM_KEY); } catch {}
        if (invite) { this.form.code = invite.toUpperCase(); }
        else if (saved) {
            try { await this.enter(await gameClient.carta.room(saved)); }
            catch { this.forget(); }
        }
        this.loading = false;
        this.$nextTick(renderIcons);
    },
    destroy() { this.stopWatch?.(); },

    async enter(room) {
        this.stopWatch?.();
        try { sessionStorage.setItem(ROOM_KEY, room.id); } catch {}
        this.seenMove = null;
        this.accept(room);
        // Reverb pushes changes instantly; a 2-second check covers dropped sockets, so no status is shown.
        this.stopWatch = gameClient.watchRoom(room.id, () => this.refresh(), () => {});
    },
    forget() {
        this.stopWatch?.(); this.stopWatch = null; this.room = null; this.screen = 'home';
        try { sessionStorage.removeItem(ROOM_KEY); } catch {}
    },
    accept(room) {
        if (!room?.id) return;
        if (this.room?.id === room.id && this.room.version > room.version) return; // Never step back to an older table.
        this.announce(room.game);
        this.room = room;
        this.screen = room.game ? 'table' : 'lobby';
        if (!room.game) this.suitPicker = null;
        this.$nextTick(renderIcons);
    },
    // Short notices for what other players did since the last update (finishes, penalties, reshuffles).
    announce(game) {
        const moves = game?.recent || [];
        const last = moves.length ? moves[moves.length - 1].id : null;
        if (this.seenMove !== null) {
            moves.filter(move => move.id > this.seenMove).forEach(move => {
                const who = move.seat === this.room?.viewerIndex ? 'You' : move.name;
                const text = {
                    finish: `${who} finished ${ordinal(move.amount)}${move.amount === 1 ? ' and won!' : '!'}`,
                    lose: `${who} ${who === 'You' ? 'are' : 'is'} the last one holding cards.`,
                    accept_draw: `${who} drew ${move.amount} penalty cards.`,
                    accept_skip: `${who} ${who === 'You' ? 'were' : 'was'} skipped.`,
                    reshuffle: 'The discard pile was reshuffled into the deck.',
                }[move.type];
                if (text) this.notify(text, move.type);
            });
        }
        this.seenMove = last ?? this.seenMove;
    },
    notify(text, kind = 'info') {
        const toast = { id: Date.now() + Math.random(), text, kind };
        this.toasts = [...this.toasts.slice(-3), toast];
        setTimeout(() => { this.toasts = this.toasts.filter(item => item.id !== toast.id); }, 4200);
    },

    async refresh() {
        if (!this.room || this.busy) return;
        try { this.accept(await gameClient.carta.room(this.room.id)); }
        catch (error) {
            // A closed, banned or lost seat ends the session; other failures are retried on the next check.
            if ([403, 404, 410].includes(error.status)) { this.error = error.message; this.forget(); }
        }
    },
    async run(task) {
        if (this.busy) return false;
        this.busy = true; this.error = '';
        try { await task(); return true; }
        catch (error) { this.error = error.message; return false; }
        finally { this.busy = false; }
    },
    createRoom() { return this.run(async () => this.enter(await gameClient.carta.create(this.form.name.trim(), this.form.capacity))); },
    joinRoom() { return this.run(async () => this.enter(await gameClient.carta.join(this.form.code.trim(), this.form.joinName.trim()))); },
    // Each action carries a fresh id so a retried request is applied at most once on the server.
    async act(action, payload = {}) {
        if (!this.room) return false;
        const ok = await this.run(async () => {
            const actionId = ['play', 'draw', 'accept', 'pass'].includes(action) ? crypto.randomUUID() : undefined;
            const next = await gameClient.carta.action(this.room.id, action, this.room.version, actionId ? { ...payload, actionId } : payload);
            if (next?.id) this.accept(next);
        });
        if (!ok && this.room) { const message = this.error; await this.refresh(); this.error = message; }
        return ok;
    },
    play(card) {
        if (!card.playable || this.busy) return;
        if (card.rank === 7 && this.game.hand.length > 1) { this.suitPicker = card.id; return; }
        this.act('play', { card: card.id });
    },
    async chooseSuit(suit) {
        const card = this.suitPicker;
        this.suitPicker = null;
        if (card) await this.act('play', { card, suit });
    },
    async leave() {
        if (await this.act('leave')) this.forget();
    },
    async copy(kind) {
        const text = kind === 'link' ? `${location.origin}/carta?join=${this.room.code}` : this.room.code;
        try { await navigator.clipboard.writeText(text); this.copied = kind; setTimeout(() => { if (this.copied === kind) this.copied = ''; }, 2000); }
        catch { this.error = 'Copy this ' + (kind === 'link' ? 'link: ' : 'code: ') + text; }
    },
});
