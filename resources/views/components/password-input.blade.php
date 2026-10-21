{{--
    A password field with a show/hide eye-icon toggle (same component as the
    wwp admin panel).

    Usage (drop-in replacement for the <input> itself — keep your own
    <label>/@error() around it as usual):
        <x-password-input id="password" name="password" autocomplete="current-password" />

    Any attribute not listed in @props (name, value, placeholder,
    autocomplete, required, autofocus, extra classes, etc.) passes straight
    through onto the rendered <input>.

    The show/hide behavior is one delegated handler — see
    `[data-toggle-password]` in resources/js/app.js.
--}}
@props(['id', 'icon' => 'lock'])

<div class="form-input-group">
    <i data-lucide="{{ $icon }}"></i>
    <input type="password"
           id="{{ $id }}"
           {{ $attributes->merge(['class' => 'form-input form-input-icon-start form-input-icon-end']) }}>
    <button type="button" class="form-input-action" data-toggle-password="{{ $id }}" tabindex="-1" aria-label="{{ __('Show password') }}">
        <i data-lucide="eye" class="w-4 h-4"></i>
    </button>
</div>
