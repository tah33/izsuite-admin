{{-- The day changed defaults start, as the country's next version (Doc 15 section 15.19 - Phase F3a). One waits at a time. Expects $versions. --}}
@php
    $today   = now()->toDateString();
    $waiting = $versions->firstWhere('state', \App\Models\Admin\CountryVersion::SCHEDULED)['version'] ?? null;
@endphp
<div class="divider"></div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="form-label" for="effective_on">{{ __('Changed defaults start') }}</label>
        <input type="date" id="effective_on" name="effective_on" min="{{ $today }}" value="{{ old('effective_on', $today) }}" class="form-input" @disabled($waiting)>
        @error('effective_on')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <p class="md:col-span-2 self-end text-xs text-[var(--text-muted)]">
        @if($waiting)
            {{ __('Version :number starts on :date. Remove it, under Versions, to change the defaults again.', ['number' => $waiting->number, 'date' => $waiting->starts_on->format('M d, Y')]) }}
        @else
            {{ __('A change to the defaults is the country\'s next version, from this day. Businesses set up from then start from it; those already set up keep their own settings.') }}
        @endif
    </p>
</div>
