import Alpine from 'alpinejs';
import draftDuel from './draftDuel';
import { reducedMotion } from './animations';
import { createIcons, Layers2 } from 'lucide';

window.Alpine = Alpine;
Alpine.data('draftDuel', draftDuel);
Alpine.start();
// Server-rendered admin pages have no Alpine component to draw their icons.
if (document.querySelector('.admin-rooms')) createIcons({ icons: { Layers2 }, attrs: { 'aria-hidden': 'true' } });

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
