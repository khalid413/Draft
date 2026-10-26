import { gameClient } from './gameClient';
import { celebrate } from './animations';

export const onlineRoom = {
    room: null, playStyle: 'local', editingRoom: false, connectionStatus: 'Connecting…',
    joinCode: '', joinName: '', copiedCode: false, copiedInvite: false, copyTimer: null, roomRefreshPending: false,
    stopRoomWatch: null, playbackKey: '', adminClicks: [],
    adminTap() {
        const now = performance.now();
        this.adminClicks = this.adminClicks.filter(at => now - at < 1200);
        this.adminClicks.push(now);
        if (this.adminClicks.length >= 3) location.assign('/admin/login');
    },
    roomPayload() {
        return { ...this.settings, version: this.room?.version, name: this.settings.players[0].trim(), capacity: this.settings.players.length };
    },
    async createRoom() {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try {
            const room = this.editingRoom ? await gameClient.roomSettings(this.room.id, this.roomPayload()) : await gameClient.createRoom(this.roomPayload());
            this.editingRoom = false; await this.connectRoom(room);
        } catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    async joinRoom() {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try { await this.connectRoom(await gameClient.joinRoom(this.joinCode, this.joinName)); }
        catch (error) { this.error = error.message; }
        finally { this.busy = false; }
    },
    async connectRoom(room) {
        this.stopRoomWatch?.(); this.copiedCode = false; this.copiedInvite = false; this.playStyle = 'online'; this.game = null; this.room = null; this.playbackKey = '';
        try { sessionStorage.setItem('draft-room-id', room.id); } catch {}
        await this.acceptRoom(room);
        this.stopRoomWatch = gameClient.watchRoom(room.id, () => this.refreshRoom(), status => { this.connectionStatus = status; });
    },
    async refreshRoom() {
        if (!this.room || this.busy || this.simulating || this.roomRefreshPending) return;
        this.roomRefreshPending = true;
        try { await this.acceptRoom(await gameClient.room(this.room.id)); }
        catch (error) {
            this.error = error.message;
            if ([403,404,410].includes(error.status)) this.disconnectRoom();
            else this.connectionStatus = 'Connection interrupted';
        } finally { this.roomRefreshPending = false; }
    },
    async acceptRoom(room) {
        if (!room?.id || !Array.isArray(room.members)) return;
        if (this.room?.id === room.id && this.room.version > room.version) return;
        const changed = this.room?.version !== room.version || this.room?.id !== room.id;
        this.room = room;
        this.offset = room.serverTimeMs - Date.now();
        if (changed) {
            await this.accept(room.game, room.game?.phase !== 'finished');
            this.offset = room.serverTimeMs - Date.now();
            if (!this.editingRoom || room.game) this.screen = room.game ? 'arena' : 'lobby';
            if (room.game?.phase === 'finished' && room.game.domainKey !== 'football') celebrate(this.isDraw);
        }
        const playback = room.playback;
        const key = playback ? `${playback.index}:${playback.startsAt}` : '';
        if (key && key !== this.playbackKey) {
            this.playbackKey = key;
            if (Date.now() + this.offset < playback.startsAt + 15000) {
                this.selectedMatchIndex = playback.index; this.simulating = true;
                await this.$nextTick();
                document.querySelector('.match-arena')?.scrollIntoView({ block: 'start', behavior: 'instant' });
                await this.playMatch(this.matches[playback.index], playback.startsAt);
                await this.$nextTick(); celebrate(this.isDraw);
            }
        }
    },
    async roomAction(action, payload = {}) {
        if (this.busy || !this.room) return false;
        this.busy = true; this.error = '';
        try {
            const next = await gameClient.roomAction(this.room.id, action, this.room.version, payload);
            if (next?.id) await this.acceptRoom(next);
            return true;
        } catch (error) {
            this.error = error.message;
            try { await this.acceptRoom(await gameClient.room(this.room.id)); } catch {}
            return false;
        } finally { this.busy = false; }
    },
    editRoomSettings() {
        this.settings = { ...this.room.settings, players: Array.from({length:this.room.settings.capacity}, (_, i) => this.room.members[i]?.name || ''), catalog: this.room.settings.catalog || '' };
        this.playStyle = 'online'; this.editingRoom = true; this.screen = 'setup';
    },
    // The code is what friends type on the Join screen; the link is an optional shortcut.
    copyCode() { return this.copyText(this.room.code, 'copiedCode', 'Copy this room code: ' + this.room.code); },
    copyInvite() { const link = `${location.origin}/?join=${this.room.code}`; return this.copyText(link, 'copiedInvite', 'Copy this invite link: ' + link); },
    async copyText(text, flag, fallback) {
        try {
            await navigator.clipboard.writeText(text);
            clearTimeout(this.copyTimer);
            this.copiedCode = false; this.copiedInvite = false; // Only the latest copy shows as done.
            this[flag] = true;
            this.copyTimer = setTimeout(() => { this[flag] = false; }, 2000);
        } catch { this.error = fallback; }
    },
    disconnectRoom() {
        this.stopRoomWatch?.(); this.stopRoomWatch = null; this.room = null; this.game = null;
        try { sessionStorage.removeItem('draft-room-id'); } catch {}
        this.screen = 'modes';
    },
    async leaveRoom() {
        if (await this.roomAction('leave')) { this.closeModal(); this.disconnectRoom(); }
    },
};
