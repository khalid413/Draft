<section x-show="simulating" class="match-arena" aria-label="Football match simulation" :class="{'match-goal': goalMoment, 'match-fulltime': matchMinute === 90}">
    <div class="match-topline"><span class="match-brand">DRAFT DUEL <b>LIVE</b></span><span class="match-clock"><i class="match-live-dot"></i><span x-text="matchMinute === 90 ? 'FULL TIME' : matchMinute + '′'"></span></span></div>
    <div class="match-score">
        <div class="match-team team-a"><div class="match-crest" x-text="matchPlayers?.[0]?.name.slice(0,2).toUpperCase()"></div><div><h2 x-text="matchPlayers?.[0]?.name"></h2><small>4–3–3 <span>·</span> <b x-text="matchOvr(0)"></b> OVR</small></div></div>
        <div class="match-scoreboard"><strong><b x-text="matchGoals[0]"></b><em>–</em><b x-text="matchGoals[1]"></b></strong><span x-text="matchMinute === 0 ? 'KICK OFF' : matchMinute === 90 ? 'FULL TIME' : (matches.length > 1 ? 'LEAGUE MATCH' : 'THE FINAL')"></span></div>
        <div class="match-team team-b"><div class="match-crest" x-text="matchPlayers?.[1]?.name.slice(0,2).toUpperCase()"></div><div><h2 x-text="matchPlayers?.[1]?.name"></h2><small>4–3–3 <span>·</span> <b x-text="matchOvr(1)"></b> OVR</small></div></div>
    </div>
    <div class="match-stadium">
        <div class="stadium-light light-left"></div><div class="stadium-light light-right"></div>
        <div class="match-field" :class="{'attack-right': matchEvent?.team === 0}">
            <div class="match-half"></div><div class="match-circle"></div><div class="match-box left"></div><div class="match-box right"></div><div class="match-six left"></div><div class="match-six right"></div><div class="match-net left"></div><div class="match-net right"></div>
            <template x-for="(slot, i) in game?.formation || []" :key="slot.key"><div>
                <div class="match-dot team-a" :style="{left: (8 + (100 - slot.y) * .38) + '%', top: (10 + slot.x * .8) + '%'}"><img :src="lineupCard(matchPlayers[0], slot.key)?.image_path" alt=""><small x-text="slot.position"></small></div>
                <div class="match-dot team-b" :style="{left: (92 - (100 - slot.y) * .38) + '%', top: (90 - slot.x * .8) + '%'}"><img :src="lineupCard(matchPlayers[1], slot.key)?.image_path" alt=""><small x-text="slot.position"></small></div>
            </div></template>
            <div class="match-ball" :style="{left: ballX + '%', top: ballY + '%'}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#fff"/><path d="m12 7 5 4-2 6H9l-2-6zM3 8l4 3M21 8l-4 3M6 20l3-3m9 3-3-3M12 2v5" fill="#172137" stroke="#172137" stroke-width="1.3"/></svg></div>
            <div class="match-kickoff" x-show="matchMinute === 0 || matchMinute === 90"><span x-text="matchMinute === 0 ? 'LET’S PLAY' : 'FULL TIME'"></span></div>
        </div>
        <div class="goal-ribbon" x-show="goalMoment" :class="goalMoment?.team === 0 ? 'team-a' : 'team-b'"><img :src="goalMoment?.scorer?.image_path" alt=""><div><b>GOAL<span>!</span></b><p x-text="goalMoment?.scorer?.name"></p></div><strong x-text="goalMoment?.minute + '′'"></strong></div>
    </div>
    <div class="match-footer"><span class="match-commentary" x-text="matchCaption" role="status"></span><span class="match-formation-note">4–3–3 <i>vs</i> 4–3–3</span></div>
    <div class="match-progress"><i :style="{width: matchMinute / 90 * 100 + '%'}"></i><template x-for="(goal, i) in matchGoalLog" :key="i"><b :class="goal.team === 0 ? 'team-a' : 'team-b'" :style="{left: goal.minute / 90 * 100 + '%'}" :title="matchPlayers[goal.team].name + ' · ' + goal.minute + '′'"></b></template></div>
</section>
