@props(['name', 'title'])
<div x-cloak x-show="modal === '{{ $name }}'" x-transition.opacity class="modal-overlay" @click.self="closeModal()" @keydown.escape.window="closeModal()">
    <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}" tabindex="-1" @keydown.tab="trapFocus($event)">
        <div class="flex items-center justify-between gap-4"><h2 id="modal-{{ $name }}">{{ $title }}</h2><button class="icon-button" @click="closeModal()" aria-label="Close dialog"><i data-icon="x-lg"></i></button></div>
        {{ $slot }}
    </section>
</div>
