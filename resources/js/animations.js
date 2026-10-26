import gsap from 'gsap';
import VanillaTilt from 'vanilla-tilt';
import { CountUp } from 'countup.js';
import confetti from 'canvas-confetti';
import { Howl, Howler } from 'howler';

export const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const sounds = Object.fromEntries(['pick', 'flip', 'reveal', 'win', 'draw'].map(name => [name, new Howl({ src: [`/sounds/${name}.wav`], volume: .26, preload: true })]));
export function muteSounds(muted) { Howler.mute(muted); }
export function sound(name) { sounds[name]?.play(); }
// Animation completion must never leave the game locked when a renderer pauses
// or a tween is interrupted. Resolve independently of GSAP's thenable objects.
function animate(start, durationMs) {
    return new Promise(resolve => {
        let tween;
        let completed = false;
        const finish = () => { if (completed) return; completed = true; clearTimeout(timeout); resolve(); };
        const timeout = setTimeout(() => { tween?.progress(1); tween?.kill(); finish(); }, durationMs + 700);
        tween = start(finish);
    });
}
function move(target, options) {
    const durationMs = (options.duration || 0) * (1 + (options.repeat || 0)) * 1000;
    return animate(done => gsap.to(target, { ...options, onComplete: done, onInterrupt: done }), durationMs);
}
function moveFrom(target, from, options) {
    return animate(done => gsap.fromTo(target, from, { ...options, onComplete: done, onInterrupt: done }), (options.duration || 0) * (1 + (options.repeat || 0)) * 1000);
}
export function tiltCards() {
    document.querySelectorAll('.draft-card').forEach(el => el.vanillaTilt?.destroy());
    if (reducedMotion() || !matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    VanillaTilt.init(document.querySelectorAll('.draft-card:not(:disabled)'), { max: 7, speed: 500, glare: true, 'max-glare': .12 });
}
export async function revealCards(update) {
    sound('flip');
    const el = document.querySelector('[data-slot="1"] .card-rotator');
    if (reducedMotion() || !el) { await update(); return; }
    await move(el, { rotation: 2, duration: .07, repeat: 5, yoyo: true });
    await move(el, { rotation: 0, rotationY: 90, duration: .24, ease: 'power2.in' });
    await update();
    const front = document.querySelector('[data-slot="1"] .card-rotator');
    await moveFrom(front, { rotationY: -90 }, { rotationY: 0, duration: .4, ease: 'power2.out' });
    document.querySelectorAll('[data-power]').forEach(el => new CountUp(el, Number(el.dataset.power), { duration: 1.1 }).start());
    sound('reveal');
}
export async function flyCards(game) {
    if (reducedMotion()) return;
    await Promise.all([0, 1].map(async slot => {
        const source = document.querySelector(`[data-slot="${slot}"]`);
        const player = slot === game.pickedSlot ? game.activePlayer : game.recipientPlayer;
        const destination = document.getElementById(`tray-${player}`);
        if (!source || !destination) return;
        const from = source.getBoundingClientRect(), to = destination.getBoundingClientRect();
        const ghost = source.cloneNode(true);
        // A flight is a static visual copy, not another Alpine component.
        // Re-evaluating cloned directives outside x-for loses the card/slot scope.
        [ghost, ...ghost.querySelectorAll('*')].forEach(node => {
            for (const attribute of [...node.attributes]) {
                if (/^(x-|@|:)/.test(attribute.name) || attribute.name === 'id' || attribute.name === 'data-slot') node.removeAttribute(attribute.name);
            }
        });
        ghost.querySelectorAll('template').forEach(node => node.remove());
        ghost.setAttribute('x-ignore', '');
        ghost.inert = true;
        ghost.setAttribute('aria-hidden', 'true');
        ghost.classList.add('flying-card');
        Object.assign(ghost.style, { position: 'fixed', left: `${from.left}px`, top: `${from.top}px`, width: `${from.width}px`, height: `${from.height}px`, zIndex: 40, pointerEvents: 'none' });
        document.body.appendChild(ghost);
        await move(ghost, { x: to.left + to.width/2 - from.left - from.width/2, y: to.top - from.top, scale: .16, opacity: .3, duration: .75, ease: 'power3.inOut' });
        ghost.remove();
        gsap.fromTo(destination, { filter: 'brightness(1.7)' }, { filter: 'brightness(1)', duration: .5 });
    }));
}
export function enterPass() {
    if (!reducedMotion()) gsap.fromTo('.pass-screen', { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: .5 });
}
export async function iconReveal(game, slot) {
    if (game.domainKey !== 'football' || !game.cards?.[slot]?.is_icon) return;
    const card = document.querySelector(`[data-slot="${slot}"] .card-front`);
    if (!card || reducedMotion()) return;
    sound('win');
    const rect = card.getBoundingClientRect();
    confetti({ particleCount: 42, spread: 65, startVelocity: 18, gravity: .8,
        origin: { x: (rect.left + rect.width/2)/innerWidth, y: (rect.top + rect.height/2)/innerHeight },
        colors: ['#FFC857', '#fff4d0', '#d6aa56'], disableForReducedMotion: true });
    await moveFrom(card, { boxShadow: '0 0 0px #FFC857', filter: 'brightness(1)' },
        { boxShadow: '0 0 65px #FFC85799', filter: 'brightness(1.3)', duration: .35, repeat: 3, yoyo: true });
    gsap.set(card, { clearProps: 'filter,boxShadow' });
}
export function celebrate(draw) {
    sound(draw ? 'draw' : 'win');
    if (reducedMotion()) return;
    gsap.fromTo('.winner-trophy', { scale: .5, y: 20 }, { scale: 1, y: 0, ease: draw ? 'power2.out' : 'bounce.out', duration: 1 });
    if (draw) return;
    confetti({ particleCount: 120, spread: 85, origin: { y: .55 }, colors: ['#7C5CFF', '#00E5C3', '#FFC857'], disableForReducedMotion: true });
    gsap.fromTo('.results-screen', { filter: 'brightness(1.7)' }, { filter: 'brightness(1)', duration: .6 });
}
