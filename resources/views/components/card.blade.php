<div class="card-face card-front" :class="['rarity-' + (card.rarity || 'rare'), card.is_icon ? 'icon-player' : '']">
    <div class="football-card-top" x-show="isFootball"><span x-text="card.position"></span><span class="icon-badge" x-show="card.is_icon">★ {{ __('ICON') }}</span></div>
    <img class="card-art" :src="card.image_path" :alt="card.name" width="360" height="420">
    <div class="card-information">
        <span class="eyebrow" x-show="card.series" x-text="card.series"></span>
        <h3 x-text="card.name"></h3>
        <div class="card-power"><span><i data-icon="lightning-charge"></i><span x-text="isFootball ? t('OVR') : t('POWER')"></span></span><strong :data-power="card.power" x-text="card.power"></strong></div>
    </div>
</div>
