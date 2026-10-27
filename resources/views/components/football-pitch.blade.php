<div class="football-pitch" :aria-label="t(':name 4-3-3 lineup', { name: player.name })">
    <div class="pitch-center" aria-hidden="true"></div><div class="pitch-box pitch-box-top" aria-hidden="true"></div><div class="pitch-box pitch-box-bottom" aria-hidden="true"></div>
    <template x-for="formationSlot in game?.formation || []" :key="formationSlot.key">
        <div class="pitch-slot" :class="{'slot-filled': lineupCard(player, formationSlot.key), 'slot-current': game?.activeSlot?.key === formationSlot.key && game?.phase !== 'finished'}" :style="'left:' + formationSlot.x + '%;top:' + formationSlot.y + '%'">
            <template x-if="lineupCard(player, formationSlot.key)"><div class="pitch-player" :class="lineupCard(player, formationSlot.key).is_icon ? 'pitch-icon' : ''" :title="lineupCard(player, formationSlot.key).name + ' · ' + formationSlot.position + ' · ' + lineupCard(player, formationSlot.key).power + ' ' + t('OVR')"><img :src="lineupCard(player, formationSlot.key).image_path" :alt="lineupCard(player, formationSlot.key).name"><strong x-text="lineupCard(player, formationSlot.key).power"></strong><span x-text="lineupCard(player, formationSlot.key).name"></span></div></template>
            <span class="pitch-position" x-text="formationSlot.position"></span>
        </div>
    </template>
</div>
