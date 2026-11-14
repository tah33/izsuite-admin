{{-- The day a change to the plan's terms starts, as its next version (Doc 15 section 15.8 - Phase E3b). One waits at a time. Expects $versions. --}}
@php
    $today   = now()->toDateString();
    $waiting = $versions->firstWhere('state', \App\Models\Billing\PackageVersion::SCHEDULED)['version'] ?? null;
@endphp
<div class="divider"></div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="form-label" for="effective_on">{{ __('Changed terms start') }}</label>
        <input type="date" id="effective_on" name="effective_on" min="{{ $today }}" value="{{ old('effective_on', $today) }}" class="form-input" @disabled($waiting)>
        @error('effective_on')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
    <p class="md:col-span-2 self-end text-xs text-[var(--text-muted)]">
        @if($waiting)
            {{ __('Version :number starts on :date. Remove it, under Versions, to change the limits, branding, levels, trial or grace again.', ['number' => $waiting->number, 'date' => $waiting->starts_on->format('M d, Y')]) }}
        @else
            {{ __('A change to the limits, branding, levels, trial or grace is the plan\'s next version, from this day. Subscribers stay on the version they are on; businesses without a subscription move to it.') }}
        @endif
    </p>
</div>
