<section class="player-tray" :id="'tray-' + index" :class="playerClass(index)" :aria-label="t(':name drafted cards', { name: player.name })">
    <div class="flex items-center justify-between gap-2"><div class="flex items-center gap-3"><span class="avatar" x-text="player.name.charAt(0)"></span><div><span class="eyebrow" x-text="t('PLAYER :number', { number: '0' + (index + 1) })"></span><h3 x-text="player.name"></h3></div></div><strong class="tray-total" x-text="player.total"></strong></div>
    <x-score-bar />
    <div class="tray-cards" x-show="!isFootball">
        <template x-for="(owned, n) in player.cards" :key="owned.id"><div class="mini-card" :title="owned.name + ' · ' + t(':power power', { power: owned.power })"><img :src="owned.image_path" :alt="owned.name"><span x-text="owned.power"></span></div></template>
        <template x-for="n in Math.max(0, (game?.cardsPerPlayer || 5) - player.cards.length)" :key="'empty-' + n"><div class="empty-card" aria-label="{{ __('Empty card slot') }}">+</div></template>
    </div>
    <div x-show="isFootball"><x-football-pitch /></div>
</section>
