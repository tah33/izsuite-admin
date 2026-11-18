{{-- An incident's details, for opening and editing one (Doc 15 section 15.23). Who is responsible is chosen when it opens; after, a note changes it. --}}
@php
    $hint = 'text-xs mt-1 text-[var(--text-muted)]';
@endphp
<div class="card mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="form-label" for="title">{{ __('Title') }}</label>
            <input type="text" name="title" id="title" class="form-input" value="{{ old('title', $incident->title ?? '') }}" required maxlength="150" placeholder="{{ __('e.g. Invoice e-mails are not going out') }}">
        </div>

        <div>
            <label class="form-label" for="service">{{ __('Service') }}</label>
            <select name="service" id="service" class="form-select">
                <option value="">{{ __('No one service') }}</option>
                @foreach(\App\Support\ServiceStatus::NAMES as $value => $label)
                    <option value="{{ $value }}" @selected(old('service', $incident->service ?? $service) === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('As Service Health names it.') }}</p>
        </div>

        <div>
            <label class="form-label" for="severity">{{ __('Severity') }}</label>
            <select name="severity" id="severity" class="form-select">
                @foreach(\App\Models\Admin\Incident::SEVERITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('severity', $incident->severity ?? 'major') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <p class="{{ $hint }}">{{ __('Critical: businesses cannot work. Major: something they rely on fails. Minor: the rest.') }}</p>
        </div>

        <div>
            <label class="form-label" for="started_at">{{ __('Started') }}</label>
            <input type="datetime-local" name="started_at" id="started_at" class="form-input" value="{{ old('started_at', ($incident->started_at ?? now())->format('Y-m-d\TH:i')) }}" required>
            <p class="{{ $hint }}">{{ __('In UTC.') }}</p>
        </div>

        @unless(isset($incident))
            <div>
                <label class="form-label" for="responsible_id">{{ __('Responsible') }}</label>
                <select name="responsible_id" id="responsible_id" class="form-select">
                    <option value="">{{ __('Nobody yet') }}</option>
                    @foreach($staff as $admin)
                        <option value="{{ $admin->id }}" @selected((string) old('responsible_id', auth()->id()) === (string) $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
        @endunless

        <div class="md:col-span-2">
            <label class="form-label" for="summary">{{ __('Summary') }}</label>
            <textarea name="summary" id="summary" rows="4" class="form-input" maxlength="5000" placeholder="{{ __('What is wrong, and whom it affects.') }}">{{ old('summary', $incident->summary ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="flex items-center gap-3">
    <button type="submit" class="btn btn-primary">
        <i data-lucide="save" class="w-4 h-4"></i>
        {{ isset($incident) ? __('Save Incident') : __('Open Incident') }}
    </button>
    <a href="{{ isset($incident) ? route('admin.incidents.show', $incident->id) : route('admin.incidents.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
</div>
