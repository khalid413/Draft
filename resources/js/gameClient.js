import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { t } from './i18n';

// All network operations stay here, including room subscriptions and resync.
async function request(path, payload) {
    const multipart = payload instanceof FormData;
    const response = await fetch(path, {
        method: payload === undefined ? 'GET' : 'POST',
        credentials: 'same-origin', cache: 'no-store',
        headers: { Accept: 'application/json', ...(!multipart ? { 'Content-Type': 'application/json' } : {}),
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        ...(payload === undefined ? {} : { body: multipart ? payload : JSON.stringify(payload) }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = Object.values(data.errors || {}).flat().join(' ') || data.message || t('Unable to reach the server. Try again.');
        const error = new Error(response.status === 419 ? t('Your session expired. Reload this page to continue.') : message);
        error.status = response.status;
        throw error;
    }
    return data;
}
export const gameClient = {
    createRoom: settings => request('/rooms', settings),
    joinRoom: (code, name) => request('/rooms/join', { code, name }),
    room: id => request(`/rooms/${id}`),
    roomAction: (id, action, version, payload = {}) => request(`/rooms/${id}/${action}`, { ...payload, version }),
    roomSettings: (id, settings) => request(`/rooms/${id}/settings`, settings),
    watchRoom: (id, onChange, onConnection) => {
        const key = document.querySelector('meta[name="reverb-key"]')?.content;
        let echo = null;
        if (key) {
            echo = new Echo({ broadcaster: 'reverb', client: undefined, Pusher, key,
                wsHost: location.hostname, wsPort: Number(document.querySelector('meta[name="reverb-port"]')?.content || 8080),
                wssPort: Number(document.querySelector('meta[name="reverb-port"]')?.content || 443),
                forceTLS: location.protocol === 'https:', enabledTransports: ['ws', 'wss'],
                authorizer: channel => ({ authorize: (socketId, callback) => request('/rooms/broadcast-auth', { socket_id: socketId, channel_name: channel.name }).then(data => callback(null, data)).catch(error => callback(error, null)) }),
            });
            echo.private(`room.${id}`).listen('.room.changed', onChange);
            echo.connector.pusher.connection.bind('state_change', ({ current }) => {
                onConnection(current === 'connected' ? 'Live' : 'Reconnecting…');
                if (current === 'connected') onChange();
            });
        }
        // Recover missed events, deadlines, and brief socket outages.
        const timer = setInterval(onChange, 2000);
        return () => { clearInterval(timer); echo?.disconnect(); };
    },
    songs: () => request('/music'),
    findTable: code => request('/find-table', { code }),
    carta: {
        create: (name, capacity) => request('/carta/rooms', { name, capacity }),
        join: (code, name) => request('/carta/rooms/join', { code, name }),
        room: id => request(`/carta/rooms/${id}`),
        action: (id, action, version, payload = {}) => request(`/carta/rooms/${id}/${action}`, { ...payload, version }),
    },
    state: () => request('/game/state'),
    start: (settings, replace = false) => request('/game/start', { ...settings, replace }),
    action: (action, version, payload = {}) => request(`/game/${action}`, { ...payload, version }),
    cards: (domain = 'custom') => request(domain === 'football' ? '/football/cards' : '/cards'),
    saveCard: (form, id = null, domain = 'custom') => {
        const path = domain === 'football' ? '/football/cards' : '/cards';
        return request(id ? `${path}/${id}` : path, form);
    },
};
