import { gameClient } from './gameClient';
import { renderIcons } from './icons';
import { t, tn, ordinal } from './i18n';
import { playMoves, dealCards, banner, throwCard, returnCard } from './unoMotion';
import { sound } from './unoSound';
import { roomShare } from './roomShare';
import { roomScanner } from './roomScanner';

const ROOM_KEY = 'uno-room-id';
const COLORS = ['red', 'blue', 'green', 'yellow'];
const COLOR_NAMES = { red: 'Red', blue: 'Blue', green: 'Green', yellow: 'Yellow' };
const SYMBOLS = { skip: 'Skip', reverse: 'Reverse', draw2: 'Draw Two' };
// Where opponents sit around the oval, in seat order starting at your left.
const SEATS = { 1: ['top'], 2: ['left', 'right'], 3: ['left', 'top', 'right'], 4: ['left', 'top-left', 'top-right', 'right'], 5: ['left', 'top-left', 'top', 'top-right', 'right'] };
// UNO moves in which cards travel from the pile to a seat, translated for the shared card-flight animation.
const GAME_ACTIONS = ['play', 'draw', 'pass', 'accept', 'uno', 'catch'];
const STACKING = { off: 'Official: no stacking', same: 'Stacking: +2 on +2, +4 on +4', mixed: 'Stacking: +2 and +4 on each other' };

// Display-only client: every card, turn and ranking shown here comes from the server's view of this seat.
export default (variant = 'classic') => ({
    ...roomShare(),
    ...roomScanner(),
    variant, colors: COLORS, screen: 'home', loading: true, busy: false, error: '',
    room: null, stopWatch: null, form: { name: '', capacity: 4, stacking: 'off', code: '', joinName: '' },
    colorPicker: null, toasts: [], seenMove: null, copied: '', ownFlights: {},
    animating: false, queued: null, soundOn: sound.on, turnShown: null, later: [],
    settingsOpen: false, draft: { capacity: 4, stacking: 'off' }, kickConfirm: null, homeTab: 'create',
    clock: Date.now(), skew: 0, ticker: null, timeoutSent: null, lastTick: null,

    get game() { return this.room?.game || null; },
    get me() { return this.game?.you || null; },
    get members() { return this.room?.members || []; },
    get everyoneReady() { return this.members.length >= 2 && this.members.every(member => member.ready); },
    get myMember() { return this.members[this.room?.viewerIndex]; },
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
        if (game.pending.effect !== 'NONE') return t('+:count for you! Stack a card, or take them.', { count: game.pending.draw });
        if (this.me.canPass) return t('You drew a playable card. Play it, or keep it and pass.');
        if (!game.hand.some(card => card.playable)) return t('No playable card. Draw one from the pile.');
        return t('Your turn. Match the colour, number or symbol, or draw.');
    },
    // The cards played before the top one stay visible underneath, so a new card lands on a pile, not on an empty spot.
    get underCards() {
        const played = (this.game?.recent || []).filter(move => (move.type === 'play' || move.type === 'deal') && move.card).map(move => ({ ...move.card, key: move.id }));
        return played.slice(-4, -1);
    },
    // A loose, stable tilt per card, so the pile looks thrown but does not shuffle on every update.
    underStyle(card, index) {
        const seed = [...card.id].reduce((sum, char) => sum * 31 + char.charCodeAt(0), 7) >>> 0;
        return `--r:${(seed % 25) - 12}deg;--x:${(seed >> 5) % 9 - 4}px;--y:${(seed >> 9) % 7 - 3}px;z-index:${index}`;
    },
    get pendingText() {
        const pending = this.game?.pending;
        return !pending || pending.effect === 'NONE' ? '' : t('+:count pending', { count: pending.draw });
    },
    get modeText() { return this.stackingText(this.game ? this.game.stacking : this.room?.settings.stacking); },
    stackingText(mode) { return t(STACKING[mode] || STACKING.off); },
    colorName(color) { return color ? t(COLOR_NAMES[color]) : ''; },
    cardLabel(card) {
        if (card.value === 'wild') return t('Wild');
        if (card.value === 'wild4') return t('Wild Draw Four');
        return t(':color :value', { color: this.colorName(card.color), value: SYMBOLS[card.value] ? t(SYMBOLS[card.value]) : card.value });
    },
    ordinal, t, tn,
    backs(count) { return Array.from({ length: Math.min(count, 7) }, (_, i) => i); },
    seatPosition(index) { return (SEATS[this.opponents.length] || [])[index] || 'top'; },
    initial(name) { return (name || '?').trim().charAt(0).toUpperCase(); },
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
    get outcome() {
        const mine = this.game?.rankings?.find(row => row.seat === this.room?.viewerIndex);
        if (!mine) return 'watch';
        if (mine.rank === 1) return 'win';
        return mine.status === 'LOSER' ? 'lose' : 'place';
    },
    get outcomeTitle() { return { win: t('VICTORY'), lose: t('DEFEAT'), place: t('MATCH OVER'), watch: t('MATCH OVER') }[this.outcome]; },
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
        if (invite) { this.form.code = invite.toUpperCase(); this.homeTab = 'join'; }
        else if (saved) {
            try { await this.enter(await gameClient.uno.room(saved)); }
            catch { this.forget(); }
        }
        this.loading = false;
        this.$nextTick(renderIcons);
        this.ticker = setInterval(() => this.tick(), 200);
    },
    destroy() { this.stopWatch?.(); clearInterval(this.ticker); this.stopScan(); },
    // Turn clock: the server sets each turn's deadline; every seat counts down against it and,
    // once it has passed, reports the timeout (the server applies it once and skips the turn).
    get turnMsLeft() {
        const ends = this.game?.status === 'PLAYING' ? this.game.turnEndsAtMs : null;
        return ends ? Math.max(0, Math.min(ends - this.clock, this.game.turnSeconds * 1000)) : 0;
    },
    get turnLeft() { return Math.ceil(this.turnMsLeft / 1000); },
    get turnProgress() { return this.game ? this.turnMsLeft / (this.game.turnSeconds * 1000) : 0; },
    tick() {
        this.clock = Date.now() + this.skew;
        const game = this.game;
        if (!game || game.status !== 'PLAYING' || !game.turnEndsAtMs) return;
        const left = game.turnEndsAtMs - this.clock;
        if (this.me?.isTurn && left > 0 && left <= 3000 && this.lastTick !== Math.ceil(left / 1000)) {
            this.lastTick = Math.ceil(left / 1000);
            sound.play('tick');
        }
        if (left < -300 && this.timeoutSent !== game.turnNumber) {
            this.timeoutSent = game.turnNumber;
            gameClient.uno.action(this.room.id, 'timeout', this.room.version, { turn: game.turnNumber })
                .then(next => { if (next?.id) this.accept(next); })
                .catch(() => this.refresh());
        }
    },

    async enter(room) {
        this.stopWatch?.();
        try { sessionStorage.setItem(ROOM_KEY, room.id); } catch {}
        this.seenMove = null;
        this.accept(room);
        this.stopWatch = gameClient.watchRoom(room.id, () => this.refresh(), () => {});
    },
    forget() {
        this.stopWatch?.(); this.stopWatch = null; this.room = null; this.screen = 'home';
        try { sessionStorage.removeItem(ROOM_KEY); } catch {}
    },
    // Updates are shown one at a time: while one batch of moves is still playing out on the table,
    // newer snapshots wait (only the newest is kept) so nothing jumps ahead of the animation.
    accept(room) {
        if (!room?.id) return;
        if (this.room?.id === room.id && this.room.version > room.version) return; // Never step back to an older table.
        if (this.animating) {
            // Only genuinely newer tables wait; the 2-second check often returns the one being shown.
            if (room.version > (this.queued || this.room)?.version) this.queued = room;
            return;
        }
        const previous = this.room;
        if (room.serverTimeMs) this.skew = room.serverTimeMs - Date.now();
        this.later = [];
        const fresh = this.announce(room);
        const notices = this.later;
        this.room = room;
        this.screen = room.game ? 'table' : 'lobby';
        if (!room.game) this.colorPicker = null;
        const dealing = previous?.id === room.id && room.game && previous.game?.id !== room.game.id;
        // Cards that just entered your hand, so each can fly in face down and turn over as it lands.
        const before = new Set(previous?.game?.id === room.game?.id ? (previous?.game?.hand || []).map(card => card.id) : []);
        const newCards = previous?.game?.id === room.game?.id ? (room.game?.hand || []).filter(card => !before.has(card.id)).map(card => card.id) : [];
        this.animating = Boolean(dealing || fresh.length);
        this.$nextTick(async () => {
            renderIcons();
            const root = document.querySelector('.carta-stage');
            try {
                if (dealing) await dealCards({ root, game: room.game, viewer: room.viewerIndex });
                else if (fresh.length) await playMoves({ root, moves: fresh, game: room.game, viewer: room.viewerIndex, ownFlights: this.ownFlights, newCards });
            } catch {} // Presentation only: a failed animation must never freeze the table.
            // Notices follow the animation, so they never give away a move before it is seen.
            notices.forEach(([text, kind]) => this.notify(text, kind));
            try {
                // Your turn starting gets its own beat, after everything before it has landed.
                // Once per turn of yours (a drawn playable card keeps the same turn going).
                const turn = room.game?.turnNumber;
                if (room.game?.you?.isTurn && room.game.status === 'PLAYING' && turn !== this.turnShown && !this.queued) {
                    this.turnShown = turn;
                    sound.play('turn');
                    await banner(root, t('Your turn'), room.game.activeColor, { duration: 900, small: true });
                }
            } catch {} // Presentation only: a failed animation must never freeze the table.
            // Only the flights this batch used are spent; a refresh that arrived mid-throw must not drop yours.
            this.ownFlights = Object.fromEntries(Object.entries(this.ownFlights).filter(([id]) => !fresh.some(move => move.card?.id === id)));
            this.animating = false;
            const next = this.queued;
            this.queued = null;
            if (next) this.accept(next);
        });
    },
    toggleSound() { this.soundOn = sound.toggle(); },
    // Short notices for what happened since the last update: UNO calls, catches, finishes.
    announce(room) {
        const moves = room.game?.recent || [];
        const last = moves.length ? moves[moves.length - 1].id : null;
        const fresh = this.seenMove === null ? [] : moves.filter(move => move.id > this.seenMove);
        fresh.forEach(move => {
            // Whole sentences per case: word order and agreement differ between English, French and Arabic.
            const mine = move.seat === room.viewerIndex, name = move.name, other = move.targetName, rank = ordinal(move.amount || 0);
            const text = {
                finish: move.amount === 1 ? (mine ? t('You finished :rank and won!', { rank }) : t(':name finished :rank and won!', { name, rank })) : (mine ? t('You finished :rank!', { rank }) : t(':name finished :rank!', { name, rank })),
                lose: mine ? t('You are the last one holding cards.') : t(':name is the last one holding cards.', { name }),
                accept_draw: mine ? tn('You drew :count penalty card.|You drew :count penalty cards.', move.amount) : tn(':name drew :count penalty card.|:name drew :count penalty cards.', move.amount, { name }),
                uno: mine ? t('You called UNO!') : t(':name calls UNO!', { name }),
                caught: mine ? t(':other caught you without UNO: +2 cards.', { other }) : t(':other caught :name without UNO: +2 cards.', { name, other }),
                forfeit: mine ? '' : t(':name left the table and forfeits.', { name }),
                timeout: mine ? t('Time is up: your turn was skipped.') : t(':name ran out of time.', { name }),
                reshuffle: t('The discard pile was reshuffled into the deck.'),
            }[move.type];
            if (text) this.later.push([text, move.type]);
        });
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
        try { this.accept(await gameClient.uno.room(this.room.id)); }
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
    createRoom() { return this.run(async () => this.enter(await gameClient.uno.create(this.form.name.trim(), this.form.capacity, this.variant, this.form.stacking))); },
    joinRoom() { return this.run(async () => this.enter(await gameClient.uno.join(this.form.code.trim(), this.form.joinName.trim()))); },
    // Each game action carries a fresh id so a retried request is applied at most once on the server.
    async act(action, payload = {}) {
        if (!this.room) return false;
        // Let the last move finish before the next one. Calling UNO is the exception: it races the catch, so it never waits.
        if (this.animating && GAME_ACTIONS.includes(action) && action !== 'uno') return false;
        const ok = await this.run(async () => {
            const actionId = GAME_ACTIONS.includes(action) ? crypto.randomUUID() : undefined;
            const next = await gameClient.uno.action(this.room.id, action, this.room.version, actionId ? { ...payload, actionId } : payload);
            if (next?.id) this.accept(next);
        });
        if (!ok && this.room) { const message = this.error; await this.refresh(); this.error = message; }
        return ok;
    },
    play(card) {
        if (!card.playable || this.busy || this.animating) return;
        // A wild that is your last card needs no colour: the match is over for you.
        if ((card.value === 'wild' || card.value === 'wild4') && this.game.hand.length > 1) { this.colorPicker = card.id; return; }
        this.launch(card, { card: card.id });
    },
    async chooseColor(color) {
        const card = this.game?.hand.find(item => item.id === this.colorPicker);
        this.colorPicker = null;
        if (card) await this.launch(card, { card: card.id, color });
    },
    // One motion from hand to pile: the card is thrown the moment you tap it, while the server confirms the play.
    // If the server refuses it, the card glides back into your hand.
    async launch(card, payload) {
        const el = document.querySelector(`[data-card="${card.id}"]`);
        const flight = throwCard({ root: document.querySelector('.carta-stage'), el, face: card.image, back: this.game.back });
        this.ownFlights = { [card.id]: flight };
        if (!(await this.act('play', payload))) {
            this.ownFlights = {};
            await returnCard(flight, document.querySelector(`[data-card="${card.id}"]`) || el);
        }
    },
    // Host settings window: edits a draft, saved in one go; removing a player asks for a second tap.
    openSettings() {
        this.draft = { capacity: this.room.settings.capacity, stacking: this.room.settings.stacking };
        this.kickConfirm = null;
        this.settingsOpen = true;
        this.$nextTick(renderIcons);
    },
    get settingsChanged() {
        return this.draft.capacity !== this.room?.settings.capacity || this.draft.stacking !== this.room?.settings.stacking;
    },
    async saveSettings() {
        const changes = {};
        if (this.draft.capacity !== this.room.settings.capacity) changes.capacity = this.draft.capacity;
        if (this.draft.stacking !== this.room.settings.stacking) changes.stacking = this.draft.stacking;
        if (await this.act('settings', changes)) this.settingsOpen = false;
    },
    async kick(seat) {
        if (this.kickConfirm !== seat) {
            this.kickConfirm = seat;
            setTimeout(() => { if (this.kickConfirm === seat) this.kickConfirm = null; }, 3000);
            return;
        }
        this.kickConfirm = null;
        const name = this.members[seat]?.name;
        if (await this.act('kick', { target: seat })) this.notify(t(':name was removed from the table.', { name }), 'forfeit');
    },
    async leave() {
        if (await this.act('leave')) this.forget();
    },
    get inviteLink() { return `${location.origin}/uno/${this.variant}?join=${this.room?.code}`; },
    async copy(kind) {
        const text = kind === 'link' ? this.inviteLink : this.room.code;
        try { await navigator.clipboard.writeText(text); this.copied = kind; setTimeout(() => { if (this.copied === kind) this.copied = ''; }, 2000); }
        catch { this.error = (kind === 'link' ? t('Copy this link: :text', { text }) : t('Copy this code: :text', { text })); }
    },
});
