<section x-cloak x-show="!loading && screen === 'home'" class="welcome-screen">
    <div class="welcome-orbit" aria-hidden="true"><span>?</span><span>✦</span></div>
    <span class="eyebrow">ONE PICK CHANGES EVERYTHING</span><h1>DRAFT<span>DUEL</span></h1><p>Your instinct. Your team. Your moment.</p>
    <button class="primary-button" @click="screen = 'modes'">Enter the arena <i data-lucide="arrow-right"></i></button>
    <button x-show="game || room" class="text-link" @click="screen = room ? (room.game ? 'arena' : 'lobby') : 'arena'">Resume your game</button>
</section>

<section x-cloak x-show="!loading && screen === 'modes'" class="journey-screen">
    <span class="eyebrow">PICK YOUR GAME</span><h1>Let's play.</h1><p>Good friends. Tough choices.</p>
    <button class="mode-feature" @click="screen = 'draft-type'"><span class="mode-art"><i data-lucide="layers-2"></i><b>?</b></span><span><strong>Draft</strong><small>Keep one. Give one. Own the result.</small></span><i data-lucide="arrow-up-right"></i></button>
    <button class="secondary-button" @click="screen = 'join'">Join a room <i data-lucide="arrow-right"></i></button>
    <button class="admin-corner" @click="adminTap()" aria-label="Administrator access: activate three times"><span>· · ·</span></button>
</section>

<section x-cloak x-show="!loading && screen === 'draft-type'" class="journey-screen">
    <button class="back-button" @click="screen = 'modes'">← Back</button><h1>Choose your arena.</h1>
    <div class="arena-choices"><button @click="selectDraft('football'); screen = 'draft-rules'" class="arena-choice football-choice"><span class="choice-symbol">⚽</span><strong>Football</strong><small>Build your 4-3-3. Settle it on the pitch.</small><span class="choice-arrow">↗</span></button><button @click="selectDraft('custom'); screen = 'draft-rules'" class="arena-choice party-choice"><span class="choice-symbol">✦</span><strong>Draft Party</strong><small>Anime, heroes, and your favourite worlds.</small><span class="choice-arrow">↗</span></button></div>
</section>

<section x-cloak x-show="!loading && screen === 'draft-rules'" class="journey-screen rules-intro">
    <button class="back-button" @click="screen = 'draft-type'">← Back</button><span class="eyebrow" x-text="settings.domain === 'football' ? 'FOOTBALL' : 'DRAFT PARTY'"></span><h1>Trust your pick.</h1>
    <ol><li><b>01</b><div><strong>One revealed. One mystery.</strong><p>Double-tap the card you want. The other goes to your rival.</p></div></li><li><b>02</b><div><strong x-text="settings.domain === 'football' ? 'Every position matters.' : 'Build your collection.'"></strong><p x-text="settings.domain === 'football' ? 'Everyone drafts a full 4-3-3 with players in their natural positions.' : 'Choose a collection and draft 3, 5 or 7 cards each. Highest total power wins.'"></p></div></li><li><b>03</b><div><strong x-text="settings.domain === 'football' ? 'Let the match decide.' : 'Share the suspense.'"></strong><p x-text="settings.domain === 'football' ? 'A 15-second match uses your formation and OVR. With 3–4 players, everyone plays a mini-league.' : 'With three players, the third receives a random card. With four, rotating pairs take turns.'"></p></div></li></ol>
    <button class="primary-button" @click="editingRoom = false; screen = 'setup'">Set up the game →</button>
</section>

<section x-cloak x-show="!loading && screen === 'join'" class="journey-screen">
    <button class="back-button" @click="screen = 'modes'">← Back</button><h1>Your seat is waiting.</h1><p>Enter the code your friend shared.</p>
    <form @submit.prevent="joinRoom()" class="join-form"><label>Room code<input x-model="joinCode" required minlength="8" maxlength="8" autocomplete="off" autocapitalize="characters" placeholder="AB12CD34"></label><label>Your name<input x-model="joinName" required minlength="2" maxlength="20" autocomplete="nickname" placeholder="What should we call you?"></label><button class="primary-button" :disabled="busy" x-text="busy ? 'Joining…' : 'Join room →'"></button></form>
</section>

<section x-cloak x-show="!loading && screen === 'lobby' && room" class="journey-screen lobby-screen">
    <div class="lobby-top"><span class="eyebrow" x-text="room?.settings.domain === 'football' ? 'FOOTBALL LOBBY' : 'DRAFT PARTY LOBBY'"></span><span class="connection-pill" x-text="connectionStatus"></span></div>
    <h1>Bring your rivals.</h1><button class="room-code" @click="copyInvite()" :aria-label="'Copy invitation for room ' + room?.code"><span x-text="room?.code"></span><small x-text="copiedInvite ? 'Copied!' : 'Copy invite ↗'"></small></button>
    <div class="lobby-seats"><template x-for="i in room?.settings.capacity || 0" :key="i"><div :class="playerClass(i - 1)"><span class="seat-avatar" x-text="room?.members?.[i-1]?.name.charAt(0) || '+'"></span><strong x-text="room?.members?.[i-1]?.name || 'Waiting for a friend'"></strong><small x-text="room?.members?.[i-1] ? (room.members[i-1].ready ? '✓ Ready' : 'Not ready') : 'Open seat'"></small><span x-show="i - 1 === room?.host" class="host-tag">Host</span></div></template></div>
    <p class="lobby-settings" x-text="roomSummary"></p>
    <div class="lobby-actions"><button class="secondary-button" @click="roomAction('ready', { ready: !room.members[room.viewerIndex].ready })" :disabled="busy" x-text="room?.members?.[room?.viewerIndex]?.ready ? 'Not ready' : 'I’m ready'"></button><button x-show="room?.isHost" class="primary-button" @click="roomAction('start')" :disabled="busy || !everyoneReady">Start draft →</button></div>
    <div class="lobby-links"><button x-show="room?.isHost" class="text-link" @click="editRoomSettings()">Edit settings</button><button class="text-link" @click="openModal('leave-room')" >Leave room</button></div>
</section>
<x-modal name="leave-room" title="Leave this room?"><p class="modal-copy" x-text="room?.isHost ? (room?.members?.filter(member => !member.left).length > 1 ? 'Hosting passes to the next player and the room stays open.' + (room?.status === 'playing' ? ' Your remaining picks are made at random.' : '') : 'You are the last player, so the room will close.') : 'Your lobby seat will become available to someone else.'"></p><div class="modal-actions"><button class="secondary-button" @click="closeModal()">Stay</button><button class="primary-button" @click="leaveRoom()" :disabled="busy">Leave room</button></div></x-modal>

<x-modal name="room-info" title="Your room"><p class="modal-copy"><span x-text="room?.code"></span> · Your seat is saved on this browser.</p><div class="modal-actions"><button class="secondary-button" @click="copyInvite()" x-text="copiedInvite ? 'Copied!' : 'Copy invitation'"></button><button x-show="room?.isHost" class="secondary-button" @click="closeModal(); openModal('leave-room')">Leave room</button><button class="primary-button" @click="closeModal()">Back to game</button></div></x-modal>
