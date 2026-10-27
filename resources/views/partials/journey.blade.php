<section x-cloak x-show="!loading && screen === 'home'" class="welcome-screen">
    <a href="/" class="back-button library-back"><i data-icon="arrow-left"></i> {{ __('All games') }}</a>
    <div class="welcome-orbit" aria-hidden="true"><span>?</span><span>✦</span></div>
    <span class="eyebrow">{{ __('ONE PICK CHANGES EVERYTHING') }}</span><h1>DRAFT<span>DUEL</span></h1><p>{{ __('Your instinct. Your team. Your moment.') }}</p>
    <button class="primary-button" @click="screen = 'modes'">{{ __('Enter the arena') }} <i data-icon="arrow-right"></i></button>
    <button x-show="game || room" class="text-link" @click="screen = room ? (room.game ? 'arena' : 'lobby') : 'arena'">{{ __('Resume your game') }}</button>
</section>

<section x-cloak x-show="!loading && screen === 'modes'" class="journey-screen">
    <a href="/" class="back-button library-back"><i data-icon="arrow-left"></i> {{ __('All games') }}</a>
    <span class="eyebrow">{{ __('DRAFT DUEL') }}</span><h1>{{ __('Ready to draft?') }}</h1><p>{{ __('Good friends. Tough choices.') }}</p>
    <button class="mode-feature" @click="screen = 'draft-type'"><span class="mode-art"><i data-icon="layers"></i><b>?</b></span><span><strong>{{ __('Draft') }}</strong><small>{{ __('Keep one. Give one. Own the result.') }}</small></span><i data-icon="arrow-up-right"></i></button>
    <button class="secondary-button" @click="screen = 'join'">{{ __('Join a room') }} <i data-icon="arrow-right"></i></button>
    <button class="admin-corner" @click="adminTap()" aria-label="Administrator access: activate three times"><span>· · ·</span></button>
</section>

<section x-cloak x-show="!loading && screen === 'draft-type'" class="journey-screen">
    <button class="back-button" @click="screen = 'modes'">← {{ __('Back') }}</button><h1>{{ __('Choose your arena.') }}</h1>
    <div class="arena-choices"><button @click="selectDraft('football'); screen = 'draft-rules'" class="arena-choice football-choice"><span class="choice-symbol">⚽</span><strong>{{ __('Football') }}</strong><small>{{ __('Build your 4-3-3. Settle it on the pitch.') }}</small><span class="choice-arrow">↗</span></button><button @click="selectDraft('custom'); screen = 'draft-rules'" class="arena-choice party-choice"><span class="choice-symbol">✦</span><strong>{{ __('Draft Party') }}</strong><small>{{ __('Anime, heroes, and your favourite worlds.') }}</small><span class="choice-arrow">↗</span></button></div>
</section>

<section x-cloak x-show="!loading && screen === 'draft-rules'" class="journey-screen rules-intro">
    <button class="back-button" @click="screen = 'draft-type'">← {{ __('Back') }}</button><span class="eyebrow" x-text="settings.domain === 'football' ? t('FOOTBALL') : t('DRAFT PARTY')"></span><h1>{{ __('Trust your pick.') }}</h1>
    <ol><li><b>01</b><div><strong>{{ __('One revealed. One mystery.') }}</strong><p>{{ __('Double-tap the card you want. The other goes to your rival.') }}</p></div></li><li><b>02</b><div><strong x-text="settings.domain === 'football' ? t('Every position matters.') : t('Build your collection.')"></strong><p x-text="settings.domain === 'football' ? t('Everyone drafts a full 4-3-3 with players in their natural positions.') : t('Choose a collection and draft 3, 5 or 7 cards each. Highest total power wins.')"></p></div></li><li><b>03</b><div><strong x-text="settings.domain === 'football' ? t('Let the match decide.') : t('Share the suspense.')"></strong><p x-text="settings.domain === 'football' ? t('A 15-second match uses your formation and OVR. With 3–4 players, everyone plays a mini-league.') : t('With three players, the third receives a random card. With four, rotating pairs take turns.')"></p></div></li></ol>
    <button class="primary-button" @click="editingRoom = false; screen = 'setup'">{{ __('Set up the game') }} →</button>
</section>

<section x-cloak x-show="!loading && screen === 'join'" class="journey-screen">
    <button class="back-button" @click="screen = 'modes'">← {{ __('Back') }}</button><h1>{{ __('Your seat is waiting.') }}</h1><p>{{ __('Enter the code your friend shared.') }}</p>
    <form @submit.prevent="joinRoom()" class="join-form"><label>{{ __('Room code') }}<input x-model="joinCode" required minlength="8" maxlength="8" autocomplete="off" autocapitalize="characters" dir="ltr" placeholder="AB12CD34"></label><label>{{ __('Your name') }}<input x-model="joinName" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="{{ __('What should we call you?') }}"></label><button class="primary-button" :disabled="busy" x-text="busy ? t('Joining…') : t('Join room') + ' →'"></button></form>
</section>

<section x-cloak x-show="!loading && screen === 'lobby' && room" class="journey-screen lobby-screen">
    <div class="lobby-top"><span class="eyebrow" x-text="room?.settings.domain === 'football' ? t('FOOTBALL LOBBY') : t('DRAFT PARTY LOBBY')"></span><span class="connection-pill" :class="{ live: connectionStatus === 'Live' }" role="status"><span class="connection-dot" aria-hidden="true"></span><span x-text="t(connectionStatus)"></span></span></div>
    <h1>{{ __('Bring your rivals.') }}</h1><p class="lobby-intro">{{ __('Friends join with the room code. When every seat is ready, the host starts the draft.') }}</p>
    <div class="room-code-card"><div class="room-code-label"><span class="eyebrow">{{ __('ROOM CODE') }}</span><span class="copy-status" role="status" x-text="copiedCode ? t('Code copied') : (copiedInvite ? t('Invite link copied') : '')"></span></div>
        <div class="room-code-row"><strong class="room-code-value" dir="ltr" x-text="room?.code"></strong><div class="room-code-actions">
            <button class="icon-button" :class="{ copied: copiedCode }" @click="copyCode()" aria-label="{{ __('Copy room code') }}" title="{{ __('Copy room code') }}"><span x-show="!copiedCode"><i data-icon="copy"></i></span><span x-show="copiedCode"><i data-icon="check-lg"></i></span></button>
            <button class="icon-button" :class="{ copied: copiedInvite }" @click="copyInvite()" aria-label="{{ __('Copy invite link') }}" title="{{ __('Copy invite link') }}"><span x-show="!copiedInvite"><i data-icon="share"></i></span><span x-show="copiedInvite"><i data-icon="check-lg"></i></span></button>
        </div></div>
    </div>
    <div class="lobby-progress"><div class="lobby-meter" aria-hidden="true"><span :style="`width:${room ? lobbyReadyCount / room.settings.capacity * 100 : 0}%`"></span></div><p x-text="lobbyHint" aria-live="polite"></p></div>
    <div class="lobby-seats"><template x-for="i in room?.settings.capacity || 0" :key="i"><div class="lobby-seat" :class="playerClass(i - 1) + (room?.members?.[i-1] ? (i - 1 === room?.host && room?.hostEditing ? ' editing' : (room.members[i-1].ready ? ' ready' : '')) : ' open')">
        <span class="seat-avatar" x-text="(room?.members?.[i-1]?.name.charAt(0) || '+').toUpperCase()"></span>
        <div class="seat-info"><strong x-text="room?.members?.[i-1]?.name || t('Waiting for a friend…')"></strong><span class="seat-tags"><span x-show="room?.members?.[i-1] && i - 1 === room?.host" class="seat-tag host">{{ __('Host') }}</span><span x-show="room?.members?.[i-1] && i - 1 === room?.viewerIndex" class="seat-tag you">{{ __('You') }}</span></span></div>
        <span class="seat-state" x-text="room?.members?.[i-1] ? (i - 1 === room?.host && room?.hostEditing ? t('In settings') : (room.members[i-1].ready ? t('Ready') : t('Not ready'))) : t('Open seat')"></span>
    </div></template></div>
    <ul class="lobby-chips" aria-label="{{ __('Room settings') }}"><template x-for="chip in lobbyChips" :key="chip"><li x-text="chip"></li></template></ul>
    <div class="lobby-actions"><button x-show="!room?.members?.[room?.viewerIndex]?.ready" class="ready-button" @click="roomAction('ready', { ready: true })" :disabled="busy">{{ __('Ready') }}</button><button x-show="room?.members?.[room?.viewerIndex]?.ready" class="secondary-button" @click="roomAction('ready', { ready: false })" :disabled="busy">{{ __('Undo') }}</button><button x-show="room?.isHost" class="primary-button" @click="roomAction('start')" :disabled="busy || !everyoneReady">{{ __('Start draft') }} →</button></div>
    <div class="lobby-links"><button x-show="room?.isHost" class="text-link" @click="editRoomSettings()">{{ __('Edit settings') }}</button><button class="text-link" @click="openModal('leave-room')">{{ __('Leave room') }}</button></div>
</section>
<x-modal name="leave-room" :title="__('Leave this room?')"><p class="modal-copy" x-text="room?.isHost ? (room?.members?.filter(member => !member.left).length > 1 ? t('Hosting passes to the next player and the room stays open.') + (room?.status === 'playing' ? ' ' + t('Your remaining picks are made at random.') : '') : t('You are the last player, so the room will close.')) : (room?.status === 'finished' ? t('You will leave the room. The others can still start a rematch.') : t('Your lobby seat will become available to someone else.'))"></p><div class="modal-actions"><button class="secondary-button" @click="closeModal()">{{ __('Stay') }}</button><button class="primary-button" @click="leaveRoom()" :disabled="busy">{{ __('Leave room') }}</button></div></x-modal>

<x-modal name="room-info" :title="__('Your room')"><p class="modal-copy"><span dir="ltr" x-text="room?.code"></span> · {{ __('Your seat is saved on this browser.') }}</p><div class="modal-actions"><button class="secondary-button" @click="copyCode()" x-text="copiedCode ? t('Copied!') : t('Copy room code')"></button><button x-show="room?.isHost" class="secondary-button" @click="closeModal(); openModal('leave-room')">{{ __('Leave room') }}</button><button class="primary-button" @click="closeModal()">{{ __('Back to game') }}</button></div></x-modal>
