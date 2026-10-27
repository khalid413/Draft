<div class="turn-banner" :class="playerClass(game?.activePlayer)">
    <span class="live-dot"></span><span x-text="t(':name is drafting', { name: activeName })"></span>
    <span class="timer" x-show="game?.timerEnabled && game?.phase === 'picking'" :class="seconds <= 10 ? 'text-danger' : ''" x-text="seconds + 's'"></span>
</div>
