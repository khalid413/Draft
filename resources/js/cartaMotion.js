// Card flights on the Carta table: a played card flies from its seat to the discard pile,
// a drawn card flies face down from the draw pile to the player's seat.
// Pure decoration: the table state is already up to date when a flight starts.

const reduced = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
const box = el => el?.getBoundingClientRect();
const center = rect => ({ x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 });

function fly(image, from, to, { delay = 0, duration = 520, spin = 0, onEnd } = {}) {
    if (!from || !to) { onEnd?.(); return; }
    const card = document.createElement('img');
    card.src = image;
    card.alt = '';
    card.className = 'carta-flyer';
    card.style.width = `${to.width}px`;
    card.style.left = `${center(to).x - to.width / 2}px`;
    card.style.top = `${center(to).y - to.height / 2}px`;
    document.body.appendChild(card);
    const start = center(from), end = center(to), scale = Math.max(.4, from.width / to.width);
    const animation = card.animate([
        { transform: `translate(${start.x - end.x}px, ${start.y - end.y}px) scale(${scale}) rotate(${spin}deg)`, opacity: .2 },
        { opacity: 1, offset: .15 },
        { transform: 'translate(0, 0) scale(1) rotate(0deg)', opacity: 1 },
    ], { duration, delay, easing: 'cubic-bezier(.2,.8,.25,1)', fill: 'both' });
    animation.onfinish = () => { card.remove(); onEnd?.(); };
}

/**
 * @param {HTMLElement} root     the Carta stage
 * @param {Array} moves          moves new since the last update
 * @param {object} game          the table after those moves
 * @param {number} viewer        the viewer's seat
 * @param {object} playedFrom    card id → rect captured when the viewer clicked it
 */
export function animateMoves(root, moves, game, viewer, playedFrom = {}) {
    if (!root || reduced() || !moves.length) return;
    const seatBox = seat => box(seat === viewer ? root.querySelector('.carta-hand') : root.querySelector(`[data-seat="${seat}"] .carta-fan`));
    const drawPile = box(root.querySelector('.carta-pile.draw'));
    const discard = root.querySelector('.carta-pile.discard');
    const top = discard?.querySelector('.carta-top');
    let delay = 0;
    // Only the latest few, so a long catch-up after a reconnect does not replay the whole match.
    moves.slice(-4).forEach(move => {
        if (move.type === 'play' && move.card) {
            const last = move.card.id === game.top?.id;
            if (last && top) top.style.visibility = 'hidden';   // The real top card appears when the flight lands.
            const from = (move.seat === viewer && playedFrom[move.card.id]) || seatBox(move.seat);
            fly(move.card.image, from, box(discard), { delay, spin: move.seat === viewer ? 0 : -20, onEnd: () => { if (last && top) top.style.visibility = ''; } });
            delay += 380;
        } else if ((move.type === 'draw' || move.type === 'accept_draw') && move.amount) {
            const target = seatBox(move.seat);
            const to = target && { left: target.left + target.width / 2 - drawPile.width / 2, top: target.top + target.height / 2 - drawPile.height / 2, width: drawPile.width, height: drawPile.height };
            for (let i = 0; i < Math.min(move.amount, 6); i++) {
                fly(game.back, drawPile, to && { ...to, left: to.left + i * 10 }, { delay, duration: 460, spin: 8 });
                delay += 150;
            }
            delay += 200;
        }
    });
}
