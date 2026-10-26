import forms from '@tailwindcss/forms';
export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js'],
    theme: { extend: {
        colors: Object.fromEntries(['bg', 'surface', 'surface-2', 'primary', 'accent', 'hot', 'gold', 'text', 'muted', 'success', 'danger'].map(name => [name, `var(--${name})`])),
        fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Space Grotesk', 'sans-serif'], mono: ['JetBrains Mono', 'monospace'] },
    } },
    plugins: [forms],
};
