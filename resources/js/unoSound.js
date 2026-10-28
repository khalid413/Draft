// Small synthesized table sounds (no audio files): a flick when a card leaves, a slap when it lands,
// a swish for draws, a pop for special cards and a chime for UNO. Muting is remembered per browser.
const KEY = 'glisa-sound';
let context = null;
let noise = null;

const muted = () => { try { return localStorage.getItem(KEY) === 'off'; } catch { return false; } };

function audio() {
    if (!context) {
        const Context = window.AudioContext || window.webkitAudioContext;
        if (!Context) return null;
        context = new Context();
        // One second of white noise, reused as the raw material of paper sounds.
        noise = context.createBuffer(1, context.sampleRate, context.sampleRate);
        const data = noise.getChannelData(0);
        for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
    }
    if (context.state === 'suspended') context.resume().catch(() => {});
    return context;
}

function burstOfNoise(ctx, { type, frequency, q = 1, duration, volume, attack = .004 }) {
    const source = ctx.createBufferSource();
    source.buffer = noise;
    const filter = ctx.createBiquadFilter();
    filter.type = type; filter.frequency.value = frequency; filter.Q.value = q;
    const gain = ctx.createGain();
    const now = ctx.currentTime;
    gain.gain.setValueAtTime(0, now);
    gain.gain.linearRampToValueAtTime(volume, now + attack);
    gain.gain.exponentialRampToValueAtTime(.0001, now + duration);
    source.connect(filter).connect(gain).connect(ctx.destination);
    source.start(now, Math.random() * .5);
    source.stop(now + duration + .02);
}

function tone(ctx, { frequency, to = frequency, duration, volume, type = 'sine', delay = 0 }) {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    const now = ctx.currentTime + delay;
    osc.type = type;
    osc.frequency.setValueAtTime(frequency, now);
    osc.frequency.exponentialRampToValueAtTime(to, now + duration);
    gain.gain.setValueAtTime(0, now);
    gain.gain.linearRampToValueAtTime(volume, now + .01);
    gain.gain.exponentialRampToValueAtTime(.0001, now + duration);
    osc.connect(gain).connect(ctx.destination);
    osc.start(now);
    osc.stop(now + duration + .02);
}

const SOUNDS = {
    flick: ctx => burstOfNoise(ctx, { type: 'bandpass', frequency: 3200, q: .8, duration: .07, volume: .18 }),
    land: ctx => { burstOfNoise(ctx, { type: 'lowpass', frequency: 1400, duration: .12, volume: .32 }); tone(ctx, { frequency: 140, to: 70, duration: .12, volume: .22 }); },
    draw: ctx => burstOfNoise(ctx, { type: 'highpass', frequency: 1800, duration: .16, volume: .1, attack: .05 }),
    effect: ctx => tone(ctx, { frequency: 320, to: 760, duration: .16, volume: .14, type: 'triangle' }),
    wild: ctx => [523, 659, 784].forEach((frequency, i) => tone(ctx, { frequency, duration: .22, volume: .08, type: 'triangle', delay: i * .06 })),
    uno: ctx => { tone(ctx, { frequency: 660, duration: .18, volume: .16, type: 'square' }); tone(ctx, { frequency: 990, duration: .3, volume: .14, type: 'square', delay: .12 }); },
    turn: ctx => tone(ctx, { frequency: 587, to: 880, duration: .2, volume: .1, type: 'sine' }),
    tick: ctx => tone(ctx, { frequency: 1200, duration: .05, volume: .07, type: 'square' }),
};

export const sound = {
    get on() { return !muted(); },
    toggle() {
        try { localStorage.setItem(KEY, muted() ? 'on' : 'off'); } catch {}
        if (!muted()) this.play('turn');
        return !muted();
    },
    play(name) {
        if (muted() || document.hidden) return;
        try { const ctx = audio(); if (ctx) SOUNDS[name]?.(ctx); } catch {} // Sound is decoration; it never breaks the game.
    },
};
