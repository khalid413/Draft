// Bootstrap Icons, inlined as SVG so existing `.x svg` sizing rules keep working.
// Usage in markup: <i data-icon="copy"></i>. Add an icon here before using it.
import arrowCounterclockwise from 'bootstrap-icons/icons/arrow-counterclockwise.svg?raw';
import arrowLeft from 'bootstrap-icons/icons/arrow-left.svg?raw';
import arrowLeftRight from 'bootstrap-icons/icons/arrow-left-right.svg?raw';
import arrowRight from 'bootstrap-icons/icons/arrow-right.svg?raw';
import arrowUpRight from 'bootstrap-icons/icons/arrow-up-right.svg?raw';
import checkLg from 'bootstrap-icons/icons/check-lg.svg?raw';
import copy from 'bootstrap-icons/icons/copy.svg?raw';
import image from 'bootstrap-icons/icons/image.svg?raw';
import layers from 'bootstrap-icons/icons/layers.svg?raw';
import lightningCharge from 'bootstrap-icons/icons/lightning-charge.svg?raw';
import lock from 'bootstrap-icons/icons/lock.svg?raw';
import musicNoteBeamed from 'bootstrap-icons/icons/music-note-beamed.svg?raw';
import pauseFill from 'bootstrap-icons/icons/pause-fill.svg?raw';
import phone from 'bootstrap-icons/icons/phone.svg?raw';
import playFill from 'bootstrap-icons/icons/play-fill.svg?raw';
import share from 'bootstrap-icons/icons/share.svg?raw';
import skipBackward from 'bootstrap-icons/icons/skip-backward.svg?raw';
import skipForward from 'bootstrap-icons/icons/skip-forward.svg?raw';
import stars from 'bootstrap-icons/icons/stars.svg?raw';
import trophy from 'bootstrap-icons/icons/trophy.svg?raw';
import volumeMute from 'bootstrap-icons/icons/volume-mute.svg?raw';
import volumeUp from 'bootstrap-icons/icons/volume-up.svg?raw';
import xLg from 'bootstrap-icons/icons/x-lg.svg?raw';

const icons = {
    'arrow-counterclockwise': arrowCounterclockwise, 'arrow-left': arrowLeft, 'arrow-left-right': arrowLeftRight,
    'arrow-right': arrowRight, 'arrow-up-right': arrowUpRight, 'check-lg': checkLg, copy, image, layers,
    'lightning-charge': lightningCharge, lock, 'music-note-beamed': musicNoteBeamed, 'pause-fill': pauseFill, phone,
    'play-fill': playFill, share, 'skip-backward': skipBackward, 'skip-forward': skipForward, stars, trophy,
    'volume-mute': volumeMute, 'volume-up': volumeUp, 'x-lg': xLg,
};

const template = document.createElement('template');

export function renderIcons(root = document) {
    root.querySelectorAll('i[data-icon]').forEach(placeholder => {
        const markup = icons[placeholder.dataset.icon];
        if (!markup) return;
        template.innerHTML = markup.trim();
        const svg = template.content.firstElementChild;
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        placeholder.replaceWith(svg);
    });
}
