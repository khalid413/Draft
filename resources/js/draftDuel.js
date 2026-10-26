import { gameClient } from './gameClient';
import { onlineRoom } from './onlineRoom';
import { createCardActivation } from './cardActivation';
import { sound, muteSounds, tiltCards, revealCards, flyCards, celebrate, enterPass, iconReveal } from './animations';
import { Notyf } from 'notyf';
import { createIcons, ImagePlus, ArrowRight, ArrowUpRight, ArrowLeft, ArrowRightLeft, Check, CircleHelp, Clapperboard, Flame, Goal, Layers2, LockKeyhole, RotateCcw, ShieldCheck, Shuffle, Smartphone, Sparkles, Swords, Timer, Trophy, UsersRound, Volume2, VolumeX, X, Zap } from 'lucide';

const icons = { ImagePlus, ArrowRight, ArrowUpRight, ArrowLeft, ArrowRightLeft, Check, CircleHelp, Clapperboard, Flame, Goal, Layers2, LockKeyhole, RotateCcw, ShieldCheck, Shuffle, Smartphone, Sparkles, Swords, Timer, Trophy, UsersRound, Volume2, VolumeX, X, Zap };
const notify = new Notyf({ duration: 3500, position: { x: 'right', y: 'bottom' } });
const drawIcons = () => createIcons({ icons, attrs: { 'aria-hidden': 'true' } });

export default (initialCardCount = 0, initialFootballInventory = [], initialCatalogs = []) => ({
    ...onlineRoom,
    get online() { return !!this.room; },
    get myTurn() { return !this.online || this.room.viewerIndex === this.game?.activePlayer; },
    get everyoneReady() { return Array.isArray(this.room?.members) && this.room.members.length === this.room.settings.capacity && this.room.members.every(member => member.ready); },
    get roomSummary() { const s = this.room?.settings; return s ? (s.domain === 'football' ? '4–3–3' : (s.catalog || 'All characters') + ' · ' + s.rounds + ' rounds') + ' · ' + (s.timer ? '30s timer' : 'No timer') : ''; },
    selectedMatchIndex: 0,
    get matches() { return this.game?.results?.league?.matches || (this.game?.results?.match ? [this.game.results.match] : []); },
    get matchPlayers() { return (this.matches[this.selectedMatchIndex]?.playerIndices || [0, 1]).map(index => this.game?.players?.[index]); },
    matchOvr(index) { return this.game?.results?.scores?.[this.matches[this.selectedMatchIndex]?.playerIndices?.[index] ?? index]?.ovr; },
    leaguePoints(index) { return this.game?.results?.league?.table.find(row => row.playerIndex === index)?.points || 0; },
    playerClass(index) { return ["player-one", "player-two", "player-three", "player-four"][index] || ""; },
    setPlayerCount(count) { this.settings.players = Array.from({ length: count }, (_, i) => this.settings.players[i] || ""); },
    goalMoment: null, matchGoalLog: [], simulating: false, matchMinute: 0, matchGoals: [0, 0], matchEvent: null, matchCaption: 'Kick off', ballX: 50, ballY: 50, matchFrame: null,
    game: null, loading: true, busy: false, screen: 'home',
    error: '', selected: null, modal: null, returnFocus: null, muted: true, seconds: 30, offset: 0,
    cardActivation: createCardActivation(),
    tick: null, settings: { players: ['', ''], domain: 'custom', catalog: '', rounds: 5, timer: false },
    catalogs: initialCatalogs, libraryFilter: '',
    get catalogOptions() { return [...new Set([...this.catalogs.map(item => item.name), 'DC Heroes'])].sort().map(name => ({ name, count: Number(this.catalogs.find(item => item.name === name)?.count || 0) })); },
    get selectedCardCount() { return this.settings.catalog ? Number(this.catalogs.find(item => item.name === this.settings.catalog)?.count || 0) : this.cardCount; },
    get filteredLibrary() { return this.libraryDomain === 'custom' && this.libraryFilter ? this.library.filter(card => card.series === this.libraryFilter) : this.library; },
    library: [], cardCount: initialCardCount, libraryLoading: false, savingCard: false,
    libraryDomain: 'custom', pendingLibraryDomain: 'custom', footballInventory: initialFootballInventory,
    positions: ['GK', 'LB', 'CB', 'RB', 'CM', 'LW', 'ST', 'RW'],
    cardForm: { id: null, name: '', series: '', power: 500, position: 'GK', is_icon: false }, cardFile: null, cardPreview: '', cardError: '',
    get isFootball() { return this.game?.domainKey === 'football'; },
    get draftFootballInventory() { return this.footballInventory.map(item => ({ ...item, needed: item.needed / 2 * this.settings.players.length, missing: Math.max(0, item.needed / 2 * this.settings.players.length - item.count) })); },
    get footballMissing() { return this.draftFootballInventory.reduce((sum, item) => sum + item.missing, 0); },
    get libraryCount() { return this.libraryDomain === 'football' ? this.footballInventory.reduce((sum, item) => sum + item.count, 0) : this.cardCount; },
    get cardsNeeded() { return Math.max(0, this.settings.rounds * this.settings.players.length - this.selectedCardCount); },
    get activeName() { return this.game?.players?.[this.game.activePlayer]?.name || ''; },
    get isDraw() { return (this.game?.results?.winners?.length || 0) > 1; },
    get winnerText() { return this.isDraw ? (this.game.results.winners.map(i => this.game.players[i].name).join(' & ') + ' share the crown.') : `${this.game?.players?.[this.game?.results?.winners?.[0]]?.name || ''} takes the crown.`; },
    async init() {
        try { this.muted = localStorage.getItem('draft-duel-muted') !== 'false'; } catch {}
        muteSounds(this.muted);
        const invitation = new URLSearchParams(location.search).get('join');
        let savedRoom = null; try { savedRoom = sessionStorage.getItem('draft-room-id'); } catch {}
        if (this.$el.dataset.admin === 'true') { this.loading = false; await this.openLibrary('custom'); }
        else if (invitation) { this.joinCode = invitation; this.screen = 'join'; }
        else if (savedRoom) {
            try { await this.connectRoom(await gameClient.room(savedRoom)); }
            catch { this.disconnectRoom(); }
        } else await this.refresh(true);
        this.loading = false;
        this.$nextTick(drawIcons);
        this.tick = setInterval(() => this.timerTick(), 250);
        this.unloadHandler = event => {
            if ((this.game && this.game.phase !== 'finished') || this.cardFile || this.cardForm.name) { event.preventDefault(); event.returnValue = ''; }
        };
        window.addEventListener('beforeunload', this.unloadHandler);
        this.visibilityHandler = () => { if (!document.hidden && !this.busy) this.refresh(); };
        document.addEventListener('visibilitychange', this.visibilityHandler);
    },
    destroy() { this.stopRoomWatch?.(); cancelAnimationFrame(this.matchFrame); this.releasePreview(); clearInterval(this.tick); window.removeEventListener('beforeunload', this.unloadHandler); document.removeEventListener('visibilitychange', this.visibilityHandler); },
    selectDraft(domain) {
        this.settings.domain = domain;
        if (domain !== 'custom') this.settings.catalog = '';
        this.settings.rounds = domain === 'football' ? 11 : (this.settings.rounds === 11 ? 5 : this.settings.rounds);
    },
    updateLibraryCounts() {
        if (this.libraryDomain === 'football') {
            this.footballInventory = this.footballInventory.map(item => {
                const count = this.library.filter(card => card.position === item.position).length;
                return { ...item, count, missing: Math.max(0, item.needed - count) };
            });
        } else {
            this.cardCount = this.library.length;
            const counts = new Map();
            this.library.forEach(card => { if (card.series) counts.set(card.series, (counts.get(card.series) || 0) + 1); });
            this.catalogs = [...counts.entries()].map(([name, count]) => ({ name, count }));
        }
    },
    lineupCard(player, key) { return player?.cards.find(card => card.slot === key); },
    async openLibrary(domain = null) {
        if (this.$el.dataset.admin !== 'true') return;
        if (this.busy || this.savingCard || this.libraryLoading) return;
        domain ??= (this.game?.domainKey || this.settings.domain) === 'football' ? 'football' : 'custom';
        this.pendingLibraryDomain = domain;
        if (this.game && this.game.phase !== 'finished') { this.openModal('library'); return; }
        if (domain !== this.libraryDomain && (this.cardFile || this.cardForm.name) && !window.confirm('Discard the unsaved card changes?')) return;
        if (domain !== this.libraryDomain) { this.libraryDomain = domain; this.resetCard(); }
        this.screen = 'library'; this.libraryLoading = true; this.cardError = ''; this.error = '';
        this.library = [];
        try { this.library = await gameClient.cards(domain); this.updateLibraryCounts(); }
        catch (error) { this.cardError = error.message; }
        finally { this.libraryLoading = false; this.$nextTick(drawIcons); }
    },
    async endAndOpenLibrary() {
        if (await this.act('abandon')) { this.closeModal(); await this.openLibrary(this.pendingLibraryDomain); }
    },
    releasePreview() { if (this.cardPreview.startsWith('blob:')) URL.revokeObjectURL(this.cardPreview); },
    resetCard() {
        this.releasePreview(); this.cardPreview = ''; this.cardFile = null;
        this.cardForm = { id: null, name: '', series: '', power: this.libraryDomain === 'football' ? 80 : 500, position: 'GK', is_icon: false }; this.cardError = '';
        if (this.$refs.cardImage) this.$refs.cardImage.value = '';
    },
    editCard(card) {
        if (this.savingCard) return;
        if ((this.cardFile || this.cardForm.name) && !window.confirm('Discard the unsaved card changes?')) return;
        this.resetCard(); this.cardForm = { id: card.id, name: card.name, series: card.series || '', power: card.power, position: card.position || 'GK', is_icon: !!card.is_icon }; this.cardPreview = card.image_path;
        this.$refs.cardName?.focus(); this.$refs.cardEditor?.scrollIntoView({ block: 'nearest' });
    },
    selectImage(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            this.cardError = 'Choose a JPG, PNG or WebP image up to 5 MB.'; event.target.value = ''; return;
        }
        this.releasePreview(); this.cardFile = file; this.cardPreview = URL.createObjectURL(file); this.cardError = '';
    },
    async saveCard() {
        if (this.savingCard) return;
        this.cardError = '';
        if (!this.cardForm.id && !this.cardFile) { this.cardError = 'Choose a picture for your card.'; return; }
        const data = new FormData(); data.append('name', this.cardForm.name);
        if (this.libraryDomain === 'custom') data.append('series', this.cardForm.series.trim());
        data.append(this.libraryDomain === 'football' ? 'ovr' : 'power', String(this.cardForm.power));
        if (this.libraryDomain === 'football') { data.append('position', this.cardForm.position); data.append('is_icon', this.cardForm.is_icon ? '1' : '0'); }
        if (this.cardFile) data.append('image', this.cardFile);
        this.savingCard = true;
        try {
            const card = await gameClient.saveCard(data, this.cardForm.id, this.libraryDomain);
            const index = this.library.findIndex(item => item.id === card.id);
            if (index < 0) this.library.unshift(card); else this.library.splice(index, 1, card);
            this.updateLibraryCounts(); const savedSeries = card.series; this.resetCard();
            if (this.libraryDomain === 'custom') { this.cardForm.series = savedSeries; this.libraryFilter = savedSeries; }
            notify.success(this.libraryDomain === 'football' ? 'Player saved' : 'Card saved');
            this.$nextTick(() => this.$refs.cardName?.focus());
        } catch (error) { this.cardError = error.message; }
        finally { this.savingCard = false; }
    },
    leaveLibrary(next = 'home') {
        if (this.savingCard) return;
        if ((this.cardFile || this.cardForm.name) && !window.confirm('Discard the unsaved card changes?')) return;
        this.resetCard(); if (this.$el.dataset.admin === 'true') { location.assign('/'); return; } if (next === 'setup') this.selectDraft(this.libraryDomain); this.screen = next;
    },
    async refresh(initial = false) {
        if (this.online) return this.refreshRoom();
        try {
            const state = await gameClient.state();
            this.error = '';
            await this.accept(state, false);
            if (initial && state && location.pathname !== '/setup') this.screen = 'arena';
        } catch (error) { this.error = error.message; }
    },
    async accept(state, animate = true) {
        state = state?.phase ? state : null;
        if (state?.version !== this.game?.version) this.cardActivation.reset();
        const oldPhase = this.game?.phase;
        const playMatch = !this.online && animate && state?.phase === 'finished' && oldPhase !== 'finished' && (state.results?.match || state.results?.league);
        if (playMatch) this.simulating = true;
        const update = async () => {
            this.game = state;
            if (state) {
                this.offset = state.serverTime * 1000 - Date.now();
                if (state.phase !== 'picking') this.selected = null;
            }
            await this.$nextTick(); drawIcons(); tiltCards();
        };
        if (animate && state?.phase === 'revealing' && oldPhase === 'picking') {
            await revealCards(update); await iconReveal(state, 1); await flyCards(state);
        } else await update();
        if (animate && state?.phase === 'picking' && oldPhase === 'pass_device') await iconReveal(state, 0);
        if (animate && state?.phase === 'pass_device') enterPass();
        if (playMatch) { this.selectedMatchIndex = 0; await this.playMatch(this.matches[0]); await this.$nextTick(); }
        if (animate && state?.phase === 'finished' && oldPhase !== 'finished') celebrate(this.isDraw);
    },
    async replayMatch(index = 0) {
        if (this.online) return this.roomAction('replay', { matchIndex: index });
        const match = this.matches[index];
        if (this.busy || this.simulating || this.game?.phase !== 'finished' || !match) return;
        this.selectedMatchIndex = index;
        this.busy = true;
        this.simulating = true;
        try {
            await this.$nextTick();
            document.querySelector('.match-arena')?.scrollIntoView({ block: 'start', behavior: 'instant' });
            await this.playMatch(match);
            await this.$nextTick();
            celebrate(this.isDraw);
        } finally {
            this.simulating = false;
            this.busy = false;
        }
    },
    async playMatch(match, serverStart = null) {
        if (!match) { this.simulating = false; return; }
        this.matchMinute = 0; this.matchGoals = [0, 0]; this.matchEvent = null;
        this.goalMoment = null; this.matchGoalLog = [];
        this.matchCaption = this.matches.length > 1 ? 'Every point counts.' : 'Two teams. One final.'; this.ballX = 50; this.ballY = 50;
        await this.$nextTick();
        const started = performance.now(); let cursor = 0, goalUntil = 0;
        const duration = 15000;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        await new Promise(resolve => {
            const frame = now => {
                const elapsed = serverStart === null ? now - started : Date.now() + this.offset - serverStart;
                const progress = Math.max(0, Math.min(1, (elapsed - 550) / (duration - 1200)));
                this.matchMinute = Math.floor(progress * 90);
                while (cursor < match.events.length && match.events[cursor].minute <= this.matchMinute) {
                    const event = match.events[cursor++]; this.matchEvent = event;
                    if (event.type === 'goal') {
                        this.matchGoals[event.team]++;
                        const forwards = this.matchPlayers[event.team].cards.filter(card => ['LW', 'ST', 'RW'].includes(card.position));
                        this.goalMoment = { ...event, scorer: forwards[event.minute % forwards.length] };
                        this.matchGoalLog.push(event); goalUntil = now + 1000;
                        this.matchCaption = this.matchPlayers[event.team].name + ' finds the net'; sound('reveal');
                    } else if (now >= goalUntil) this.matchCaption = this.matchPlayers[1 - event.team].name + ' holds firm';
                }
                if (now >= goalUntil) this.goalMoment = null;
                if (!reduced && progress > 0 && progress < 1) {
                    const next = match.events[cursor];
                    const lastMinute = cursor ? match.events[cursor - 1].minute : 0;
                    const phase = next ? Math.min(1, (progress * 90 - lastMinute) / Math.max(1, next.minute - lastMinute)) : 0;
                    const direction = next?.team === 0 ? 1 : -1;
                    this.ballX = 50 + direction * phase * 43;
                    this.ballY = 50 + Math.sin(phase * Math.PI * 2) * 17 * (1 - phase);
                }
                if (progress === 1) { this.goalMoment = null; this.matchCaption = 'The final whistle.'; this.matchGoals = [...match.goals]; }
                if (elapsed < duration) this.matchFrame = requestAnimationFrame(frame);
                else { this.simulating = false; this.matchFrame = null; resolve(); }
            };
            this.matchFrame = requestAnimationFrame(frame);
        });
    },
    async act(action, payload = {}) {
        if (this.online) return this.roomAction(action, payload);
        if (this.busy || !this.game) return false;
        this.busy = true; this.error = '';
        try { await this.accept(await gameClient.action(action, this.game.version, payload)); return true; }
        catch (error) { this.error = error.message; notify.error(error.message); await this.refresh(); this.error = error.message; return false; }
        finally { this.busy = false; }
    },
    requestStart() {
        if (this.playStyle === 'online') return this.createRoom();
        if (this.online) this.disconnectRoom();
        if (new Set(this.settings.players.map(name => name.trim().toLowerCase())).size !== this.settings.players.length) {
            this.error = 'Choose a different name for each player.'; return;
        }
        if (this.game && this.game.phase !== 'finished') this.openModal('replace');
        else this.start();
    },
    async start(replace = false) {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try {
            const state = await gameClient.start(this.settings, replace);
            this.closeModal(); this.screen = 'arena'; this.selected = null;
            this.library = []; // Do not keep the catalog in the active-game UI.
            await this.accept(state); notify.success('The arena is yours. Good luck!');
        } catch (error) { this.error = error.message; notify.error(error.message); }
        finally { this.busy = false; }
    },
    playAgain() {
        this.settings = { players: this.game.players.map(p => p.name), domain: this.game.domainKey, catalog: this.game.catalog || '', rounds: this.game.cardsPerPlayer || this.game.roundLimit, timer: this.game.timerEnabled };
        this.start();
    },
    choose(slot) { if (!this.myTurn || this.busy || this.game?.phase !== 'picking' || this.game.pickedSlot !== null) return; this.selected = slot; sound('pick'); },
    cardClick(slot, event) {
        if (this.busy || this.game?.phase !== 'picking' || this.game.pickedSlot !== null) return;
        this.choose(slot);
        // Keyboard-generated clicks highlight only; Enter below commits the selected card.
        if (event.detail === 0) { this.cardActivation.reset(); return; }
        if (this.cardActivation.tap(this.game.cards[slot].selectionId, performance.now())) this.confirmPick();
    },
    doubleClickCard(slot) {
        if (this.busy || this.game?.phase !== 'picking' || this.game.pickedSlot !== null) return;
        this.choose(slot);
        this.confirmPick();
    },
    async confirmPick() {
        if (this.selected === null || this.busy || this.game?.phase !== 'picking' || this.game.pickedSlot !== null) return;
        this.cardActivation.reset();
        const token = this.game.cards[this.selected].selectionId;
        const success = await this.act('pick', { playerIndex: this.game.activePlayer, cardId: token });
        if (success && !this.online) await this.act('reveal');
    },
    recipientName(slot) { if (!this.game) return ''; return this.game.players[slot === this.game.pickedSlot ? this.game.activePlayer : this.game.recipientPlayer]?.name; },
    timerTick() {
        if (this.game?.phase !== 'picking' || !this.game.pickDeadline || this.game.pickedSlot !== null) return;
        this.seconds = Math.max(0, Math.ceil((this.game.pickDeadline * 1000 - Date.now() - this.offset) / 1000));
        if (this.seconds === 0 && !this.busy && !this.error) { this.closeModal(); this.act('timeout'); }
    },
    toggleMute() { this.muted = !this.muted; muteSounds(this.muted); try { localStorage.setItem('draft-duel-muted', String(this.muted)); } catch {} if (!this.muted) sound('pick'); },
    goHome() { if (this.simulating) return; if (this.screen === 'library') this.leaveLibrary(); else if (this.screen === 'arena' && this.game?.phase !== 'finished') this.openModal('leave'); else this.screen = 'modes'; },
    openModal(name) {
        this.returnFocus = document.activeElement; this.modal = name;
        this.$nextTick(() => document.querySelector(`[x-show="modal === '${name}'"] .modal-panel`)?.focus());
    },
    closeModal() { if (!this.modal) return; this.modal = null; this.returnFocus?.focus(); },
    trapFocus(event) {
        const focusable = [...event.currentTarget.querySelectorAll('button:not(:disabled), input, [tabindex="0"]')];
        const first = focusable[0], last = focusable.at(-1);
        if (event.shiftKey && (document.activeElement === first || document.activeElement === event.currentTarget)) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    },
    keyboard(event) {
        if (!this.myTurn || this.modal || this.busy || this.screen !== 'arena' || this.game?.phase !== 'picking' || this.game?.pickedSlot !== null || ['INPUT', 'TEXTAREA'].includes(event.target.tagName)) return;
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); this.choose(event.key === 'ArrowLeft' ? 0 : 1); }
        if (event.key === 'Enter' && !event.repeat && (event.target === document.body || event.target.closest('.draft-card'))) {
            event.preventDefault();
            const focusedCard = event.target.closest('.draft-card');
            if (focusedCard) this.choose(Number(focusedCard.dataset.slot));
            if (this.selected !== null) this.confirmPick();
        }
    },
});
