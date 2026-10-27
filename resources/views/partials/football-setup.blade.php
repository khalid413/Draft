<div x-show="settings.domain === 'football'" class="football-setup">
    <div class="formation-summary"><strong>4-3-3</strong><span>{{ __('11 players per team') }}</span></div>
    <p>GK · LB · CB · CB · RB · CM · CM · CM · LW · ST · RW</p>

    <div class="deck-notice"><span x-text="footballMissing ? tn('The catalog needs :count more player for this room size.|The catalog needs :count more players for this room size.', footballMissing) : t('All teams are ready to draft.')"></span></div>
</div>
