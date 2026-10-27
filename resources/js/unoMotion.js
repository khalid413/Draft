// Card motion for the UNO table. Every function returns a promise, so the table can play moves one at a time:
// a card leaves the hand, arcs to the pile, lands with weight, then its effect shows, then the next move starts.
// Pure presentation: the table state is already final; this only decides when each piece of it appears.
import { sound } from './unoSound';

const reduced = () => document.hidden || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const center = rect => ({ x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 });
// A box only counts if it is on screen (opponent fans are hidden on phones).
const rectOf = el => { const rect = el?.getBoundingClientRect(); return rect && rect.width && rect.height ? rect : null; };
const plain = rect => rect && { left: rect.left, top: rect.top, width: rect.width, height: rect.height };

const TIMING = { flight: 680, land: 240, banner: 820, draw: 460, drawGap: 190, between: 160, deal: 360, dealGap: 85 };
const COLORS = { red: '#e3262f', blue: '#1e6fd9', green: '#2fa84f', yellow: '#f5c400' };

/** A two-sided card in flight; starts face down when it will flip. */
function flyer(face, back, width, faceDown) {
    const card = document.createElement('div');
    card.className = 'uno-flyer';
    card.style.width = `${width}px`;
    card.innerHTML = `<div class="uno-flyer-inner${faceDown ? ' down' : ''}"><img class="front" alt=""><img class="back" alt=""></div>`;
    card.querySelector('.front').src = face || back;
    card.querySelector('.back').src = back;
    document.body.appendChild(card);
    return card;
}

/**
 * Flies a card from one box to another along an arc. Resolves with the card element still in place,
 * so the caller decides when it disappears. A timer backs up the animation in case the tab is throttled.
 */
function fly({ face, back, from, to, flip = false, faceDown = false, duration = TIMING.flight, lift = 80, spin = 0, settle = 0, startOpacity = 1 }) {
    return new Promise(resolve => {
        if (!from || !to) { resolve(null); return; }
        const card = flyer(face, back, to.width, faceDown || flip);
        card.style.left = `${to.left}px`;
        card.style.top = `${to.top + to.height / 2 - (to.width * 140 / 85) / 2}px`;
        const start = center(from), end = center(to), dx = start.x - end.x, dy = start.y - end.y;
        const scale = Math.max(.35, from.width / to.width), peak = Math.max(scale, 1) * 1.14;
        const motion = card.animate([
            { transform: `translate(${dx}px, ${dy}px) scale(${scale}) rotate(${spin}deg)`, opacity: startOpacity, easing: 'cubic-bezier(.25,.7,.4,1)' },
            { transform: `translate(${dx * .42}px, ${dy * .42 - lift}px) scale(${peak}) rotate(${spin * .4}deg)`, opacity: 1, offset: .48, easing: 'cubic-bezier(.5,0,.75,.5)' },
            { transform: `translate(0, 0) scale(1) rotate(${settle}deg)`, opacity: 1 },
        ], { duration, fill: 'forwards' });
        if (flip) {
            card.firstElementChild.animate([{ transform: 'rotateY(180deg)' }, { transform: 'rotateY(180deg)', offset: .25 }, { transform: 'rotateY(0deg)' }],
                { duration, easing: 'ease-in-out', fill: 'forwards' });
        }
        let done = false;
        const finish = () => { if (!done) { done = true; resolve(card); } };
        motion.onfinish = finish;
        setTimeout(finish, duration + 400);
    });
}

/** The pile jolts and a ring in the card's colour spreads from it. */
function impact(discard, color) {
    if (!discard) return wait(0);
    discard.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.13) rotate(-2deg)', offset: .35 }, { transform: 'scale(1)' }],
        { duration: TIMING.land, easing: 'cubic-bezier(.3,1.6,.5,1)' });
    const ring = document.createElement('span');
    ring.className = 'uno-impact';
    ring.style.setProperty('--ring', COLORS[color] || '#fff');
    discard.appendChild(ring);
    ring.animate([{ transform: 'scale(.6)', opacity: .9 }, { transform: 'scale(1.9)', opacity: 0 }], { duration: 520, easing: 'ease-out' }).onfinish = () => ring.remove();
    return wait(TIMING.land);
}

/** A big symbol pops over the felt: ⊘ for Skip, ⇄ for Reverse, +2/+4, or a short word. */
export function banner(root, text, color, { duration = TIMING.banner, small = false } = {}) {
    const felt = root?.querySelector('.carta-oval');
    if (!felt || reduced()) return wait(0);
    const el = document.createElement('div');
    el.className = 'uno-banner' + (small ? ' small' : '');
    el.textContent = text;
    el.style.setProperty('--glow', COLORS[color] || '#f5c400');
    felt.appendChild(el);
    const motion = el.animate([
        { transform: 'translate(-50%, -50%) scale(.3) rotate(-12deg)', opacity: 0 },
        { transform: 'translate(-50%, -50%) scale(1.15) rotate(3deg)', opacity: 1, offset: .22 },
        { transform: 'translate(-50%, -50%) scale(1) rotate(0deg)', opacity: 1, offset: .7 },
        { transform: 'translate(-50%, -50%) scale(1.25)', opacity: 0 },
    ], { duration, easing: 'ease-out' });
    return new Promise(resolve => { const end = () => { el.remove(); resolve(); }; motion.onfinish = end; setTimeout(end, duration + 300); });
}

/** A wave of the new colour washes over the felt. */
function ripple(root, color) {
    const felt = root?.querySelector('.carta-oval');
    if (!felt || !COLORS[color]) return;
    const wave = document.createElement('span');
    wave.className = 'uno-ripple';
    wave.style.setProperty('--wave', COLORS[color]);
    felt.appendChild(wave);
    wave.animate([{ transform: 'translate(-50%, -50%) scale(.1)', opacity: .75 }, { transform: 'translate(-50%, -50%) scale(4)', opacity: 0 }],
        { duration: 900, easing: 'cubic-bezier(.2,.7,.3,1)' }).onfinish = () => wave.remove();
}

/** "UNO!" bursts out of a seat. */
function burst(target) {
    const box = rectOf(target);
    if (!box) return wait(0);
    const el = document.createElement('div');
    el.className = 'uno-burst';
    el.textContent = 'UNO!';
    el.style.left = `${center(box).x}px`;
    el.style.top = `${center(box).y}px`;
    document.body.appendChild(el);
    const motion = el.animate([
        { transform: 'translate(-50%, -50%) scale(.2) rotate(-20deg)', opacity: 0 },
        { transform: 'translate(-50%, -60%) scale(1.25) rotate(-6deg)', opacity: 1, offset: .25 },
        { transform: 'translate(-50%, -70%) scale(1) rotate(-6deg)', opacity: 1, offset: .75 },
        { transform: 'translate(-50%, -90%) scale(.9) rotate(-6deg)', opacity: 0 },
    ], { duration: 1000, easing: 'ease-out' });
    return new Promise(resolve => { const end = () => { el.remove(); resolve(); }; motion.onfinish = end; setTimeout(end, 1300); });
}

function spinDirection(root) {
    root?.querySelector('.uno-direction')?.animate([{ transform: 'translateY(-50%) rotate(0) scale(1)' }, { transform: 'translateY(-50%) rotate(-540deg) scale(1.6)', offset: .6 }, { transform: 'translateY(-50%) rotate(-720deg) scale(1)' }],
        { duration: 900, easing: 'cubic-bezier(.3,.7,.3,1)' });
}

const EFFECTS = { skip: '⊘', reverse: '⇄', draw2: '+2', wild4: '+4' };

/**
 * Plays a batch of new moves in order and waits for each.
 * @param {object} o
 * @param {HTMLElement} o.root      the table stage
 * @param {Array} o.moves           moves new since the last update, oldest first
 * @param {object} o.game           the table after those moves
 * @param {number} o.viewer         the viewer's seat
 * @param {object} o.playedFrom     card id → box captured when the viewer clicked it
 * @param {Array} o.newCards        ids of cards that entered the viewer's hand in this batch, in arrival order
 */
export async function playMoves({ root, moves, game, viewer, playedFrom = {}, newCards = [] }) {
    if (!root || !moves.length) return;
    const discard = root.querySelector('.carta-pile.discard');
    const top = discard?.querySelector('.carta-top');
    const drawPile = rectOf(root.querySelector('.carta-pile.draw'));
    const seatBox = seat => seat === viewer ? rectOf(root.querySelector('.carta-hand'))
        : rectOf(root.querySelector(`[data-seat="${seat}"] .carta-fan`)) || rectOf(root.querySelector(`[data-seat="${seat}"] .carta-chip`));
    // Nothing shows before it arrives: the new top card and the viewer's new cards wait for their flights.
    const arriving = newCards.map(id => root.querySelector(`[data-card="${id}"]`)).filter(Boolean);
    const lastPlay = [...moves].reverse().find(move => move.type === 'play');
    if (lastPlay && top) top.style.visibility = 'hidden';
    arriving.forEach(card => { card.style.visibility = 'hidden'; });
    const parked = [];
    const revealAll = () => { if (top) top.style.visibility = ''; arriving.forEach(card => { card.style.visibility = ''; }); parked.forEach(card => card.remove()); };
    if (reduced()) { revealAll(); return; }

    try {
        const batch = moves.slice(-6);
        for (const [index, move] of batch.entries()) {
            const finishes = batch.slice(index + 1).some(next => next.type === 'finish' && next.seat === move.seat);
            if (move.type === 'play' && move.card) {
                const mine = move.seat === viewer;
                const from = (mine && playedFrom[move.card.id]) || seatBox(move.seat);
                const to = plain(rectOf(discard));
                sound.play('flick');
                const card = await fly({ face: move.card.image, back: game.back, from, to, flip: !mine, spin: mine ? 8 : -24, settle: -3, lift: mine ? 110 : 60 });
                sound.play('land');
                const color = move.color || move.card.color;
                if (move === lastPlay) { if (top) top.style.visibility = ''; card?.remove(); } else if (card) parked.push(card);
                await impact(discard, color);
                // A finishing card's effect is cancelled, so it gets no banner.
                if (!finishes) {
                    if (move.card.value === 'wild' || move.card.value === 'wild4') { ripple(root, move.color); sound.play('wild'); }
                    if (move.card.value === 'reverse') spinDirection(root);
                    if (EFFECTS[move.card.value]) { sound.play('effect'); await banner(root, EFFECTS[move.card.value], color); }
                    else if (move.card.value === 'wild') await wait(450);
                }
            } else if (['draw', 'accept_draw', 'caught', 'challenge_lost', 'challenge_won'].includes(move.type) && move.amount) {
                const seat = move.type === 'challenge_won' ? move.target : move.seat;
                const mine = seat === viewer;
                if (move.type === 'caught') { sound.play('effect'); await banner(root, '+2', 'red', { duration: 600 }); }
                const flights = [];
                for (let i = 0; i < Math.min(move.amount, 8); i++) {
                    const target = mine ? arriving.shift() : null;
                    const to = mine && target ? plain(rectOf(target)) : (() => { const box = seatBox(seat); return box && { left: center(box).x - drawPile.width / 2 + i * 8, top: center(box).y - drawPile.height / 2, width: drawPile.width, height: drawPile.height }; })();
                    const face = target?.querySelector('img')?.src;
                    sound.play('draw');
                    flights.push(fly({ face, back: game.back, from: drawPile, to, flip: Boolean(face), faceDown: !face, duration: TIMING.draw, lift: 40, spin: 10 })
                        .then(card => { card?.remove(); if (target) target.style.visibility = ''; }));
                    await wait(TIMING.drawGap);
                }
                await Promise.all(flights);
            } else if (move.type === 'uno') {
                sound.play('uno');
                await burst(move.seat === viewer ? root.querySelector('.carta-chip.me') : root.querySelector(`[data-seat="${move.seat}"] .carta-chip`));
            }
            await wait(TIMING.between);
        }
    } finally {
        revealAll();
    }
}

/** The opening deal: one card at a time around the table, seven rounds, then the first card turns over. */
export async function dealCards({ root, game, viewer }) {
    if (!root || reduced()) return;
    const drawPile = rectOf(root.querySelector('.carta-pile.draw'));
    const mine = [...root.querySelectorAll('.carta-hand .carta-card')];
    const top = root.querySelector('.carta-pile.discard .carta-top');
    mine.forEach(card => { card.style.visibility = 'hidden'; });
    if (top) top.style.visibility = 'hidden';
    const seats = game.players.map(player => player.seat);
    const rounds = Math.max(mine.length, 7);
    const flights = [];
    try {
        for (let round = 0; round < rounds; round++) {
            for (const seat of seats) {
                if (seat === viewer) {
                    const target = mine[round];
                    if (!target) continue;
                    flights.push(fly({ face: target.querySelector('img')?.src, back: game.back, from: drawPile, to: plain(rectOf(target)), flip: true, duration: TIMING.deal + 120, lift: 50, spin: 14 })
                        .then(card => { card?.remove(); target.style.visibility = ''; }));
                } else {
                    const box = rectOf(root.querySelector(`[data-seat="${seat}"] .carta-fan`)) || rectOf(root.querySelector(`[data-seat="${seat}"] .carta-chip`));
                    const to = box && { left: center(box).x - drawPile.width / 2, top: center(box).y - drawPile.height / 2, width: drawPile.width, height: drawPile.height };
                    flights.push(fly({ back: game.back, from: drawPile, to, faceDown: true, duration: TIMING.deal, lift: 30, spin: -10 }).then(card => card?.remove()));
                }
                sound.play('draw');
                await wait(TIMING.dealGap);
            }
        }
        await Promise.all(flights);
        const discard = root.querySelector('.carta-pile.discard');
        const card = await fly({ face: game.top.image, back: game.back, from: drawPile, to: plain(rectOf(discard)), flip: true, lift: 60, settle: -3 });
        sound.play('land');
        if (top) top.style.visibility = '';
        card?.remove();
        await impact(discard, game.top.color);
    } finally {
        mine.forEach(card => { card.style.visibility = ''; });
        if (top) top.style.visibility = '';
    }
}
