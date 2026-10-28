{{-- Share a room: a large QR code to scan from another phone, the code, and the phone's share sheet. Used by Carta and UNO. --}}
<div x-cloak x-show="shareOpen && room" class="carta-overlay" role="dialog" aria-modal="true" aria-labelledby="room-share-title" @keydown.escape.window="shareOpen = false" @click.self="shareOpen = false">
    <div class="carta-results room-share">
        <h2 id="room-share-title">{{ __('Scan to join') }}</h2>
        <p class="room-share-hint">{{ __('Point a phone camera at the code: it opens this table with the code already filled in.') }}</p>
        <div class="room-share-qr" role="img" aria-label="{{ __('QR code for this table') }}" x-html="shareSvg"></div>
        <div class="room-share-code"><span class="carta-eyebrow">{{ __('TABLE CODE') }}</span><strong dir="ltr" x-text="room?.code"></strong></div>
        <div class="carta-results-actions">
            <button x-show="canNativeShare" class="carta-primary" @click="nativeShare()"><i data-icon="share"></i> {{ __('Share') }}</button>
            <button class="carta-secondary" @click="copy('link')"><span x-show="copied !== 'link'">{{ __('Copy invite link') }}</span><span x-show="copied === 'link'">{{ __('Copied!') }}</span></button>
        </div>
        <button class="text-link" @click="shareOpen = false">{{ __('Close') }}</button>
    </div>
</div>
