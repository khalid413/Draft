import QRCode from 'qrcode';
import { renderIcons } from './icons';
import { t } from './i18n';

// Share a room by QR code: drawn in the browser (no outside service sees the table code),
// plus the phone's own share sheet where there is one. Mixed into the Carta and UNO components,
// which each provide `inviteLink`.
export const roomShare = () => ({
    shareOpen: false, shareSvg: '', canNativeShare: typeof navigator !== 'undefined' && Boolean(navigator.share),

    async openShare() {
        try {
            this.shareSvg = await QRCode.toString(this.inviteLink, { type: 'svg', margin: 1, errorCorrectionLevel: 'M', color: { dark: '#111111', light: '#ffffff' } });
        } catch { this.shareSvg = ''; }
        this.shareOpen = true;
        this.$nextTick(renderIcons);
    },
    async nativeShare() {
        try { await navigator.share({ title: t('Join my table on Glisa'), text: t('Table code: :code', { code: this.room.code }), url: this.inviteLink }); }
        catch {} // Closing the share sheet is not an error.
    },
});
