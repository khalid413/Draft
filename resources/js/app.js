import './i18n'; // first: defines window.t for Alpine expressions
import Alpine from 'alpinejs';
import draftDuel from './draftDuel';
import carta from './carta';
import { gameClient } from './gameClient';
import { reducedMotion } from './animations';
import { renderIcons } from './icons';

window.Alpine = Alpine;
Alpine.data('draftDuel', draftDuel);
Alpine.data('carta', carta);
// Library join box: a table code opens whichever game it belongs to.
Alpine.data('library', () => ({
    code: '', error: '', busy: false,
    async join() {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try { location.assign((await gameClient.findTable(this.code.trim())).url); }
        catch (error) { this.error = error.message; this.busy = false; }
    },
}));
Alpine.start();
// Server-rendered pages (admin screens, the library) have no component that draws their icons.
if (document.querySelector('.admin-rooms, .glisa-shell')) renderIcons();

if (!reducedMotion()) {
    Promise.all([import('@tsparticles/engine'), import('@tsparticles/slim')]).then(async ([{ tsParticles }, { loadSlim }]) => {
        await loadSlim(tsParticles);
        await tsParticles.load({ id: 'particles', options: {
            fullScreen: { enable: false }, fpsLimit: 30, detectRetina: false,
            particles: { number: { value: 22 }, color: { value: ['#7C5CFF', '#00E5C3'] },
                opacity: { value: .22 }, size: { value: { min: 1, max: 2 } }, move: { enable: true, speed: .18 } },
            interactivity: { events: { onHover: { enable: false }, onClick: { enable: false } } },
        }});
        matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', event => {
            if (event.matches) tsParticles.dom().forEach(container => container.destroy());
        });
    }).catch(() => {}); // Decorative background must never block the game.
}
