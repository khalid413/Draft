{{-- Scan a table's QR code with the camera, inside the page. Used by the Carta and UNO join forms. --}}
<div x-cloak x-show="scanOpen" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="room-scan-title" @keydown.escape.window="scanOpen && stopScan()">
    <div class="carta-results room-scan">
        <h2 id="room-scan-title">{{ __('Scan a table') }}</h2>
        <p class="room-share-hint">{{ __('Point the camera at the QR code shown on your friend\'s screen.') }}</p>
        <div class="room-scan-view">
            <video playsinline muted aria-hidden="true"></video>
            <span class="room-scan-frame" aria-hidden="true"></span>
        </div>
        <p x-show="scanError" class="room-scan-error" role="alert" x-text="scanError"></p>
        <button class="carta-secondary" @click="stopScan()">{{ __('Cancel') }}</button>
    </div>
</div>
