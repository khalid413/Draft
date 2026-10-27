// Visitor translations. Keys are the English sentences (same as Laravel's __()), so English needs no file.
const { locale = 'en', strings = {} } = window.GLISA_I18N || {};

export { locale };

function fill(text, params) {
    // Longest names first, so :names is never cut short by :name (Laravel does the same).
    return Object.entries(params).sort(([a], [b]) => b.length - a.length).reduce((out, [key, value]) => out.replaceAll(':' + key, value), text);
}

/** t(sentence, params): sentence is the English text, params fill :placeholders. */
export function t(text, params = {}) {
    return fill(strings[text] ?? text, params);
}

// Plural forms are '|'-separated: English/French use one|other, Arabic one|two|few|other.
function pluralIndex(count) {
    if (locale === 'ar') {
        if (count === 1) return 0;
        if (count === 2) return 1;
        return count % 100 >= 3 && count % 100 <= 10 ? 2 : 3;
    }
    return (locale === 'fr' ? count <= 1 : count === 1) ? 0 : 1;
}

/** tn(pluralForms, count, params): forms are '|'-separated; :count is filled in. */
export function tn(text, count, params = {}) {
    const forms = (strings[text] ?? text).split('|');
    return fill(forms[Math.min(pluralIndex(count), forms.length - 1)], { count, ...params });
}

const ARABIC_ORDINALS = ['الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس'];

/** 1st / 1er / الأول */
export function ordinal(n) {
    if (locale === 'ar') return ARABIC_ORDINALS[n - 1] || String(n);
    if (locale === 'fr') return n === 1 ? '1er' : `${n}e`;
    return n + (['th', 'st', 'nd', 'rd'][(n % 100 - 20) % 10] || ['th', 'st', 'nd', 'rd'][n % 100] || 'th');
}

// Alpine expressions in the Blade views call these directly.
Object.assign(window, { t, tn, ordinal });
