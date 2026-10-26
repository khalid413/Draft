// Token-bound so two taps on different cards (or different rounds) never confirm.
export function createCardActivation(windowMs = 450) {
    let previous = null;
    return {
        reset() { previous = null; },
        tap(token, now) {
            const confirmed = previous?.token === token && now - previous.at <= windowMs && now >= previous.at;
            previous = confirmed ? null : { token, at: now };
            return confirmed;
        },
    };
}
