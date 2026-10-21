{{--
    Per-page picker for a list page's filter <form method="GET">. Submits the
    form on change.

    Hidden at xl and up: the topbar shows its own "Rows" picker there (same
    per_page parameter), and two of them side by side read as two different
    settings. A hidden select still submits, so the filter form keeps sending
    the current value.

    Usage: <x-per-page-select />
--}}
@props(['options' => [10, 15, 25, 50, 100], 'default' => 15])

@php($current = (int) request('per_page', $default))

<div class="w-[84px] shrink-0 xl:hidden">
    <select name="per_page" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Rows per page') }}">
        @foreach($options as $option)
            <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
        @endforeach
    </select>
</div>
