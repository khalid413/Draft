<div class="score-track" role="meter" :aria-label="isFootball ? t('Total OVR') : t('Running base power')" aria-valuemin="0" :aria-valuemax="game.cardsPerPlayer * game.powerMax" :aria-valuenow="player.total">
    <span :class="player.total > Math.max(...game.players.filter((p, i) => i !== index).map(p => p.total)) ? 'leader' : ''" :style="'width:' + Math.min(100, player.total / (game.cardsPerPlayer * game.powerMax) * 100) + '%'"></span>
</div>
