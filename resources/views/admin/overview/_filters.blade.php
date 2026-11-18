{{-- Admin > Overview's filters, in one toolbar row (Doc 15 section 15.4): the days, the businesses' plan, subscription, country and app, and the billing's currency. --}}
<form action="{{ route('admin.overview') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-2">
    <div class="w-40 shrink-0">
        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-input" aria-label="{{ __('From') }}" title="{{ __('From') }}">
    </div>
    <div class="w-40 shrink-0">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-input" aria-label="{{ __('To') }}" title="{{ __('To') }}">
    </div>
    <div class="w-40 shrink-0">
        <select name="package" class="form-select" aria-label="{{ __('Plan') }}">
            <option value="">{{ __('All plans') }}</option>
            @foreach($options['plans'] as $key => $name)
                <option value="{{ $key }}" @selected(($filters['package'] ?? null) === $key)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-44 shrink-0">
        <select name="subscription" class="form-select" aria-label="{{ __('Subscription') }}">
            <option value="">{{ __('Any subscription') }}</option>
            @foreach($options['subscriptions'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['subscription'] ?? null) === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="country" class="form-select" aria-label="{{ __('Country') }}">
            <option value="">{{ __('All countries') }}</option>
            @foreach($options['countries'] as $code => $name)
                <option value="{{ $code }}" @selected(($filters['country'] ?? null) === $code)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-44 shrink-0">
        <select name="app" class="form-select" aria-label="{{ __('App') }}">
            <option value="">{{ __('Any app') }}</option>
            @foreach($options['apps'] as $id => $name)
                <option value="{{ $id }}" @selected((string) ($filters['app'] ?? '') === (string) $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-32 shrink-0">
        <select name="currency" class="form-select" aria-label="{{ __('Currency') }}">
            <option value="">{{ __('All currencies') }}</option>
            @foreach($options['currencies'] as $code)
                <option value="{{ $code }}" @selected(($filters['currency'] ?? null) === $code)>{{ $code }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="filter" class="w-4 h-4"></i>{{ __('Filter') }}
    </button>
    <a href="{{ route('admin.overview') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</form>
<p class="text-xs mb-5 text-[var(--text-muted)]">
    {{ __('The plan, subscription, country and app pick the businesses every figure counts; the currency picks the billing\'s. Days are in UTC.') }}
</p>
