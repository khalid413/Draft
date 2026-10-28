import jsQR from 'jsqr';
import { t } from './i18n';

// Scan a table's QR code without leaving the page: the camera opens in a window, the code is read
// in the browser (the native detector where there is one, jsQR otherwise), and the join form fills itself.
// Mixed into the Carta and UNO components, which provide `form.code`.
const GAME_PATH = /^\/(carta|uno\/[a-z-]+)$/;

export const roomScanner = () => ({
    scanOpen: false, scanError: '', scanStream: null, scanTimer: null,

    async startScan() {
        this.scanError = '';
        this.scanOpen = true;
        await this.$nextTick();
        const video = document.querySelector('.room-scan video');
        try {
            this.scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        } catch {
            this.scanError = t('The camera is not available. Allow camera access, or type the code.');
            return;
        }
        if (!this.scanOpen) { this.stopScan(); return; } // Closed while the permission prompt was up.
        video.srcObject = this.scanStream;
        await video.play().catch(() => {});
        const detector = 'BarcodeDetector' in window ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const look = async () => {
            if (!this.scanOpen) return;
            let text = null;
            try {
                if (detector) {
                    text = (await detector.detect(video))[0]?.rawValue ?? null;
                } else if (video.videoWidth) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    context.drawImage(video, 0, 0);
                    const frame = context.getImageData(0, 0, canvas.width, canvas.height);
                    text = jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
                }
            } catch {} // A frame that cannot be read is just skipped.
            if (text && this.useScanned(text)) return;
            this.scanTimer = setTimeout(look, 180);
        };
        look();
    },
    stopScan() {
        this.scanOpen = false;
        clearTimeout(this.scanTimer);
        this.scanStream?.getTracks().forEach(track => track.stop());
        this.scanStream = null;
    },
    /** Accepts a Glisa invite link from this site, or a bare 8-character table code. Anything else is refused. */
    useScanned(text) {
        let code = null, path = null;
        try {
            const url = new URL(text.trim());
            if (url.origin === location.origin && GAME_PATH.test(url.pathname)) {
                code = url.searchParams.get('join');
                path = url.pathname;
            }
        } catch {
            if (/^[A-Za-z0-9]{8}$/.test(text.trim())) code = text.trim();
        }
        if (!code || !/^[A-Za-z0-9]{8}$/.test(code)) {
            this.scanError = t('That QR code is not a Glisa table.');
            return false;
        }
        this.stopScan();
        code = code.toUpperCase();
        // A code for the other game opens that game's join form instead.
        if (path && path !== location.pathname) {
            location.assign(`${path}?join=${code}`);
            return true;
        }
        this.form.code = code;
        this.homeTab = 'join';
        this.$nextTick(() => document.querySelector('input[x-model="form.joinName"]')?.focus());
        return true;
    },
});
