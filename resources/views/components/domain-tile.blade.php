@props(['name', 'icon', 'description', 'enabled' => false])
<button type="button" class="domain-tile {{ $enabled ? 'domain-active' : 'domain-disabled' }}" {{ $enabled ? '' : 'disabled' }} @if($enabled) aria-pressed="true" @endif>
    <span class="domain-icon"><i data-icon="{{ $icon }}"></i></span>
    <span class="domain-name">{{ $name }}</span>
    <span class="domain-description">{{ $description }}</span>
    <span class="domain-status">@if($enabled)<span class="live-dot"></span> READY TO DRAFT @else<i data-icon="lock"></i> COMING SOON @endif</span>
</button>
