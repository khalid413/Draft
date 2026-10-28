import { gameClient } from './gameClient';
import { renderIcons } from './icons';
import { t, tn, ordinal, locale } from './i18n';
import { animateMoves } from './cartaMotion';
import { roomShare } from './roomShare';
import { roomScanner } from './roomScanner';

const ROOM_KEY = 'carta-room-id';
// English names are translation keys; the Darija names stay as a subtitle in every other language.
const SUITS = {
    coins: { en: 'Coins', ar: 'ذهب' },
    swords: { en: 'Swords', ar: 'سيوف' },
    cups: { en: 'Cups', ar: 'طباق' },
    clubs: { en: 'Clubs', ar: 'زراوط' },
};
// Where opponents sit around the oval, in clockwise turn order starting at your left.
const SEATS = { 1: ['top'], 2: ['left', 'right'], 3: ['left', 'top', 'right'], 4: ['left', 'top-left', 'top-right', 'right'], 5: ['left', 'top-left', 'top', 'top-right', 'right'] };

// Display-only client: every card, turn and ranking shown here comes from the server's view of this seat.
export default () => ({
    ...roomShare(),
    ...roomScanner(),
    suits: SUITS, screen: 'home', loading: true, busy: false, error: '',
    room: null, stopWatch: null, form: { name: '', capacity: 4, code: '', joinName: '' },
    suitPicker: null, toasts: [], seenMove: null, copied: '', playedFrom: {},

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
        if (this.me?.status === 'FINISHED') return t('You finished :rank. Watching :name.', { rank: ordinal(this.me.rank), name: this.currentName });
        if (!this.me?.isTurn) return t(':name is playing…', { name: this.currentName });
        if (game.pending.effect === 'SKIP') return t('Skip! Answer with a 1, or accept losing your turn.');
        if (game.pending.effect === 'DRAW_TWO') return t('+:count for you! Answer with a 2, or draw them.', { count: game.pending.draw });
        if (!game.hand.some(card => card.playable)) return t('No playable card. Draw one from the pile.');
        return t('Your turn. Play a card of the same suit or number, or draw one.');
    },
    pendingText() {
        const pending = this.game?.pending;
        if (!pending || pending.effect === 'NONE') return '';
        return pending.effect === 'SKIP' ? t('Skip pending') : t('+:count pending', { count: pending.draw });
    },
    suitTitle(suit) { return suit ? (locale === 'ar' ? SUITS[suit].ar : t(SUITS[suit].en)) : ''; },
    suitSubtitle(suit) { return suit && locale !== 'ar' ? SUITS[suit].ar : ''; },
    cardLabel(card) { return t(':rank of :suit', { rank: card.rank, suit: this.suitTitle(card.suit) }); },
    ordinal, t, tn,
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
        if (this.game?.status === 'FINISHED') return t('Match over');
        if (this.me?.status === 'FINISHED') return t('Spectating');
        return this.me?.isTurn ? t('Your turn') : t('Round in progress');
    },
    // How the finished match ended for the viewer: win, a middle place, lose, or watch (no seat in the ranking).
    get outcome() {
        const mine = this.game?.rankings?.find(row => row.seat === this.room?.viewerIndex);
        if (!mine) return 'watch';
        if (mine.rank === 1) return 'win';
        return mine.status === 'LOSER' ? 'lose' : 'place';
    },
    get outcomeTitle() {
        return { win: t('VICTORY'), lose: t('DEFEAT'), place: t('MATCH OVER'), watch: t('MATCH OVER') }[this.outcome];
    },
    get outcomeText() {
        const rankings = this.game?.rankings || [];
        const mine = rankings.find(row => row.seat === this.room?.viewerIndex);
        if (this.outcome === 'win') return t('You won the match!');
        if (this.outcome === 'lose') return t('You are the last one holding cards.');
        if (this.outcome === 'place') return t('You finished :rank!', { rank: ordinal(mine.rank) });
        return t(':name won the match!', { name: rankings[0]?.name || '' });
    },
    rankTag(player) {
        if (player.status === 'LOSER') return t('Loser');
        if (player.status === 'FINISHED') return player.rank === 1 ? t('Winner · :rank', { rank: ordinal(1) }) : ordinal(player.rank);
        return player.isTurn ? t('Playing') : tn(':count card|:count cards', player.cards);
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
    destroy() { this.stopWatch?.(); this.stopScan(); },

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
        const fresh = this.announce(room.game);
        this.room = room;
        this.screen = room.game ? 'table' : 'lobby';
        if (!room.game) this.suitPicker = null;
        this.$nextTick(() => {
            renderIcons();
            if (!fresh.length) return;
            animateMoves(document.querySelector('.carta-stage'), fresh, room.game, room.viewerIndex, this.playedFrom);
            this.playedFrom = {};
        });
    },
    // Short notices for what other players did since the last update (finishes, penalties, reshuffles).
    announce(game) {
        const moves = game?.recent || [];
        const last = moves.length ? moves[moves.length - 1].id : null;
        const fresh = this.seenMove === null ? [] : moves.filter(move => move.id > this.seenMove);
        if (fresh.length) {
            fresh.forEach(move => {
                // Whole sentences per case: word order and agreement differ between English, French and Arabic.
                const mine = move.seat === this.room?.viewerIndex, name = move.name, rank = ordinal(move.amount || 0);
                const text = {
                    finish: move.amount === 1 ? (mine ? t('You finished :rank and won!', { rank }) : t(':name finished :rank and won!', { name, rank })) : (mine ? t('You finished :rank!', { rank }) : t(':name finished :rank!', { name, rank })),
                    lose: mine ? t('You are the last one holding cards.') : t(':name is the last one holding cards.', { name }),
                    accept_draw: mine ? tn('You drew :count penalty card.|You drew :count penalty cards.', move.amount) : tn(':name drew :count penalty card.|:name drew :count penalty cards.', move.amount, { name }),
                    accept_skip: mine ? t('You were skipped.') : t(':name was skipped.', { name }),
                    forfeit: mine ? '' : t(':name left the table and forfeits.', { name }),
                    reshuffle: t('The discard pile was reshuffled into the deck.'),
                }[move.type];
                if (text) this.notify(text, move.type);
            });
        }
        this.seenMove = last ?? this.seenMove;
        return fresh;
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
            const actionId = ['play', 'draw', 'accept'].includes(action) ? crypto.randomUUID() : undefined;
            const next = await gameClient.carta.action(this.room.id, action, this.room.version, actionId ? { ...payload, actionId } : payload);
            if (next?.id) this.accept(next);
        });
        if (!ok && this.room) { const message = this.error; await this.refresh(); this.error = message; }
        return ok;
    },
    play(card) {
        if (!card.playable || this.busy) return;
        // Remember where the card was, so it flies from the hand to the pile once the server accepts it.
        const rect = document.querySelector(`[data-card="${card.id}"]`)?.getBoundingClientRect();
        if (rect) this.playedFrom = { [card.id]: { left: rect.left, top: rect.top, width: rect.width, height: rect.height } };
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
    get inviteLink() { return `${location.origin}/carta?join=${this.room?.code}`; },
    async copy(kind) {
        const text = kind === 'link' ? this.inviteLink : this.room.code;
        try { await navigator.clipboard.writeText(text); this.copied = kind; setTimeout(() => { if (this.copied === kind) this.copied = ''; }, 2000); }
        catch { this.error = (kind === 'link' ? t('Copy this link: :text', { text }) : t('Copy this code: :text', { text })); }
    },
});
