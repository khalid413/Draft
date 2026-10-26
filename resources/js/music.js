import { gameClient } from './gameClient';

const STORAGE_KEY = 'draft-duel-music';
const readSaved = () => { try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch { return {}; } };

// Background music uses its own <audio> element, so muting the Howler effects never touches it.
// Kept outside Alpine state: a proxied media element throws on play().
let audio = null;
function audioFor(player) {
    if (!audio) {
        audio = new Audio();
        audio.preload = 'none';
        audio.addEventListener('ended', () => player.nextSong());
        audio.addEventListener('play', () => { player.musicPlaying = true; });
        audio.addEventListener('pause', () => { player.musicPlaying = false; });
        audio.addEventListener('error', () => { player.musicPlaying = false; player.musicError = 'This song could not be played.'; });
    }
    return audio;
}

export const musicPlayer = {
    songs: [], songsLoaded: false, musicOpen: false, musicPlaying: false, musicIndex: 0, musicVolume: .35, musicError: '',
    currentSong() { return this.songs[this.musicIndex] || null; }, // A method: spreading would freeze a getter.
    async loadSongs() {
        const saved = readSaved();
        if (typeof saved.volume === 'number') this.musicVolume = Math.min(1, Math.max(0, saved.volume));
        try { this.songs = await gameClient.songs(); } catch { this.songs = []; }
        const index = this.songs.findIndex(song => song.id === saved.id);
        this.musicIndex = index >= 0 ? index : 0;
        this.songsLoaded = true;
    },
    toggleMusicMenu() {
        this.musicOpen = !this.musicOpen;
        if (this.musicOpen) this.loadSongs(); // Pick up songs the admin added since the page loaded.
    },
    async playSong(index) {
        if (!this.songs.length) return;
        this.musicError = '';
        this.musicIndex = (index + this.songs.length) % this.songs.length;
        const player = audioFor(this);
        const src = new URL(this.currentSong().src, location.origin).href;
        if (player.src !== src) player.src = src;
        player.volume = this.musicVolume;
        this.saveMusic();
        try { await player.play(); } catch { this.musicPlaying = false; }
    },
    toggleMusic() { if (this.musicPlaying) audio?.pause(); else this.playSong(this.musicIndex); },
    nextSong() { this.playSong(this.musicIndex + 1); },
    previousSong() { this.playSong(this.musicIndex - 1); },
    setMusicVolume() { if (audio) audio.volume = this.musicVolume; this.saveMusic(); },
    saveMusic() { try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ id: this.currentSong()?.id, volume: this.musicVolume })); } catch {} },
    stopMusic() { audio?.pause(); },
};
