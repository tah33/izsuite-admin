{{-- A platform announcement's fields, for adding and editing one (Doc 15 section 15.21 - Phase F1). Expects $announcement (or null), $businesses, $plans, $countries and $apps. --}}
@php
    $hint     = 'text-xs mt-1 text-[var(--text-muted)]';
    $audience = old('audience', $announcement->audience ?? 'all');
    $named    = fn (string $list) => (array) (session()->hasOldInput() ? old($list, []) : ($announcement?->audience === $list ? ($announcement->targets ?? []) : []));
@endphp

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('The Announcement') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label" for="title">{{ __('Title') }}</label>
            <input type="text" name="title" id="title" class="form-input" value="{{ old('title', $announcement->title ?? '') }}" required maxlength="150">
        </div>
        <div class="md:col-span-2">
            <label class="form-label" for="message">{{ __('Message') }}</label>
            <textarea name="message" id="message" rows="3" class="form-input" required maxlength="1000">{{ old('message', $announcement->message ?? '') }}</textarea>
        </div>
        <div>
            <label class="form-label" for="priority">{{ __('Priority') }}</label>
            <select name="priority" id="priority" class="form-select">
                @foreach(\App\Models\Admin\Announcement::PRIORITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', $announcement->priority ?? 'info') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('The most urgent shows first: Emergency, then Important, then Information.') }}</p>
        </div>
        <div class="self-center">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="dismissible" value="1" class="toggle-switch" @checked(old('dismissible', $announcement->dismissible ?? true))>
                <span class="form-label mb-0">{{ __('Can be dismissed') }}</span>
            </label>
            <p class="{{ $hint }}">{{ __('Each person puts it away for themselves. Leave it off for an emergency or legal notice: it stays until it ends.') }}</p>
        </div>
        <div>
            <label class="form-label" for="link_url">{{ __('Link') }}</label>
            <input type="text" name="link_url" id="link_url" class="form-input" value="{{ old('link_url', $announcement->link_url ?? '') }}" maxlength="500" placeholder="https://izsuite.io/news">
            <p class="{{ $hint }}">{{ __('A web address, or a page of the app such as /settings?tab=subscription.') }}</p>
        </div>
        <div>
            <label class="form-label" for="link_label">{{ __('Link words') }}</label>
            <input type="text" name="link_label" id="link_label" class="form-input" value="{{ old('link_label', $announcement->link_label ?? '') }}" maxlength="60" placeholder="{{ __('Learn more') }}">
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('Who Sees It') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="audience">{{ __('Audience') }}</label>
            <select name="audience" id="audience" class="form-select">
                @foreach(\App\Models\Admin\Announcement::AUDIENCES as $value => $label)
                    <option value="{{ $value }}" @selected($audience === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('Businesses see it above every page of their workspace; admins, above every page here.') }}</p>
        </div>
        <div data-audience="packages">
            <span class="form-label">{{ __('Plans') }}</span>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($plans as $plan)
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="packages[]" value="{{ $plan->key }}" class="form-checkbox" @checked(in_array($plan->key, $named('packages'), true))>
                        <span class="text-sm">{{ $plan->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div data-audience="countries">
            <label class="form-label" for="countries">{{ __('Countries') }}</label>
            <select name="countries[]" id="countries" class="form-select" multiple data-placeholder="{{ __('Choose countries') }}">
                @foreach($countries as $country)
                    <option value="{{ $country['code'] }}" @selected(in_array($country['code'], $named('countries'), true))>{{ $country['name'] }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('The business\'s country, from its profile.') }}</p>
        </div>
        <div data-audience="apps">
            <label class="form-label" for="apps">{{ __('Apps') }}</label>
            <select name="apps[]" id="apps" class="form-select" multiple data-placeholder="{{ __('Choose apps') }}">
                @foreach($apps as $family => $name)
                    <option value="{{ $family }}" @selected(in_array($family, $named('apps'), true))>{{ $name }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('Businesses with one of them on, either edition.') }}</p>
        </div>
        <div data-audience="businesses" class="md:col-span-2">
            <label class="form-label" for="businesses">{{ __('Businesses') }}</label>
            <textarea name="businesses" id="businesses" rows="3" class="form-input" maxlength="5000" placeholder="BW-000012">{{ old('businesses', $businesses) }}</textarea>
            <p class="{{ $hint }}">{{ __('Their BW- IDs, one a line or with commas.') }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <h3 class="section-title mb-4">{{ __('When') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="form-label" for="starts_at">{{ __('From') }}</label>
            <input type="datetime-local" name="starts_at" id="starts_at" class="form-input" value="{{ old('starts_at', $announcement?->starts_at?->format('Y-m-d\TH:i')) }}">
            <p class="{{ $hint }}">{{ __('In UTC. Empty: as soon as it is saved.') }}</p>
        </div>
        <div>
            <label class="form-label" for="ends_at">{{ __('Until') }}</label>
            <input type="datetime-local" name="ends_at" id="ends_at" class="form-input" value="{{ old('ends_at', $announcement?->ends_at?->format('Y-m-d\TH:i')) }}">
            <p class="{{ $hint }}">{{ __('In UTC. Empty: until it is deleted.') }}</p>
        </div>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>{{ isset($announcement) ? __('Save Announcement') : __('Add Announcement') }}</button>
    <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
</div>

@push('scripts')
    <script>
        // Only the list the audience names is asked for.
        $(function () {
            var $audience = $('#audience');
            var toggle = function () { $('[data-audience]').each(function () { $(this).toggle($(this).data('audience') === $audience.val()); }); };
            $audience.on('change', toggle);
            toggle();
        });
    </script>
@endpush
