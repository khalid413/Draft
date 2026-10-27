{{-- Shared admin navbar. Pass $active: cards | rooms | music. --}}
@php
    $sections = [
        'cards' => ['href' => '/admin', 'icon' => 'layers', 'label' => 'Cards'],
        'rooms' => ['href' => route('admin.rooms'), 'icon' => 'door-open', 'label' => 'Rooms'],
        'music' => ['href' => route('admin.music'), 'icon' => 'music-note-list', 'label' => 'Music'],
    ];
@endphp
<header class="site-header admin-header">
    <a href="/" class="wordmark" aria-label="Glisa player site"><span class="brand-mark glisa-admin-mark" aria-hidden="true">ج</span><span class="wordmark-text">GLISA</span><span class="admin-badge">Admin</span></a>
    <nav class="admin-tabs" aria-label="Admin sections">
        @foreach($sections as $key => $section)
            <a href="{{ $section['href'] }}" @class(['admin-tab', 'active' => $active === $key]) @if($active === $key) aria-current="page" @endif><i data-icon="{{ $section['icon'] }}"></i><span>{{ $section['label'] }}</span></a>
        @endforeach
    </nav>
    <form method="POST" action="/admin/logout" class="admin-signout">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out"><i data-icon="box-arrow-right"></i></button></form>
</header>
