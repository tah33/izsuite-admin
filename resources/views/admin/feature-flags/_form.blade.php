{{-- A feature flag's fields, for adding and editing one (Doc 15 section 15.12). Its key is fixed once made: the code asks by it. --}}
@php
    $hint   = 'text-xs mt-1 text-[var(--text-muted)]';
    $picked = fn (string $list) => (array) (session()->hasOldInput() ? old($list, []) : ($flag->{$list} ?? []));
@endphp

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('The Feature') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="key">{{ __('Key') }}</label>
            @if(isset($flag))
                <input type="text" id="key" class="form-input" value="{{ $flag->key }}" disabled>
            @else
                <input type="text" name="key" id="key" class="form-input" value="{{ old('key') }}" required maxlength="60" placeholder="{{ __('e.g. new_reports') }}">
            @endif
            <p class="{{ $hint }}">{{ __('What the code asks by: Features::enabled(\'key\'). Fixed once made.') }}</p>
        </div>
        <div>
            <label class="form-label" for="name">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $flag->name ?? '') }}" required maxlength="100">
        </div>
        <div class="md:col-span-2">
            <label class="form-label" for="description">{{ __('Description') }}</label>
            <textarea name="description" id="description" rows="2" class="form-input" maxlength="2000">{{ old('description', $flag->description ?? '') }}</textarea>
        </div>
        <div>
            <label class="form-label" for="status">{{ __('Switch') }}</label>
            <select name="status" id="status" class="form-select">
                <option value="off" @selected(old('status', $flag->status ?? 'off') === 'off')>{{ __('Off') }}</option>
                <option value="on" @selected(old('status', $flag->status ?? 'off') === 'on')>{{ __('On') }}</option>
            </select>
            <p class="{{ $hint }}">{{ __('Off overrides everything: the emergency switch.') }}</p>
        </div>
        <div>
            <label class="form-label" for="responsible_id">{{ __('Responsible') }}</label>
            <select name="responsible_id" id="responsible_id" class="form-select">
                <option value="">{{ __('Nobody') }}</option>
                @foreach($staff as $admin)
                    <option value="{{ $admin->id }}" @selected((string) old('responsible_id', $flag->responsible_id ?? auth()->id()) === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-1">{{ __('Who Gets It') }}</h3>
    <p class="text-sm mb-4 text-[var(--text-muted)]">{{ __('The businesses named always. Then a share of the businesses in the plans and countries chosen - none chosen, all - the same ones each time. Internal testing: name izSuite\'s own businesses and leave the share at 0%.') }}</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label" for="businesses">{{ __('Businesses') }}</label>
            <textarea name="businesses" id="businesses" rows="3" class="form-input" maxlength="5000" placeholder="BW-000012">{{ old('businesses', $businesses) }}</textarea>
            <p class="{{ $hint }}">{{ __('Their BW- IDs, one a line or with commas.') }}</p>
        </div>
        <div>
            <span class="form-label">{{ __('Plans') }}</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($plans as $plan)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="packages[]" value="{{ $plan->key }}" class="form-checkbox" @checked(in_array($plan->key, $picked('packages'), true))>
                        <span class="text-sm">{{ $plan->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="form-label" for="countries">{{ __('Countries') }}</label>
            <select name="countries[]" id="countries" class="form-select" multiple data-placeholder="{{ __('Every country') }}">
                @foreach($countries as $country)
                    <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $picked('countries'), true))>{{ $country['name'] }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('The business\'s country, from its profile.') }}</p>
        </div>
        <div>
            <label class="form-label" for="percentage">{{ __('Share (%)') }}</label>
            <input type="number" name="percentage" id="percentage" class="form-input" min="0" max="100" step="1" value="{{ old('percentage', $flag->percentage ?? 0) }}" required>
            <p class="{{ $hint }}">{{ __('100: all of them. A business in 10% stays in at 20%.') }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('When') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="starts_at">{{ __('From') }}</label>
            <input type="datetime-local" name="starts_at" id="starts_at" class="form-input" value="{{ old('starts_at', $flag?->starts_at?->format('Y-m-d\TH:i')) }}">
            <p class="{{ $hint }}">{{ __('In UTC. Empty: as soon as it is on.') }}</p>
        </div>
        <div>
            <label class="form-label" for="ends_at">{{ __('Until') }}</label>
            <input type="datetime-local" name="ends_at" id="ends_at" class="form-input" value="{{ old('ends_at', $flag?->ends_at?->format('Y-m-d\TH:i')) }}">
            <p class="{{ $hint }}">{{ __('In UTC. Empty: no end.') }}</p>
        </div>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>{{ isset($flag) ? __('Save Flag') : __('Add Flag') }}</button>
    <a href="{{ route('admin.feature-flags.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
</div>
