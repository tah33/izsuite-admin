{{-- Admin > Businesses' filters, in one toolbar row (Doc 15 section 15.5). .form-input and .form-select are full width, so each sits in its own sized box. --}}
<form action="{{ route('admin.businesses.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <div class="search-input-wrapper flex-1 min-w-[220px]">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, BW- ID, or the owner\'s name or e-mail...') }}" class="form-input search-input w-full">
    </div>
    <div class="w-40 shrink-0">
        <select name="status" class="form-select" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Any status') }}</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="package" class="form-select" aria-label="{{ __('Plan') }}">
            <option value="">{{ __('All plans') }}</option>
            @foreach($packages as $package)
                <option value="{{ $package->key }}" @selected(request('package') === $package->key)>{{ $package->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-44 shrink-0">
        <select name="subscription" class="form-select" aria-label="{{ __('Subscription') }}">
            <option value="">{{ __('Any subscription') }}</option>
            @foreach($subscriptions as $value => $label)
                <option value="{{ $value }}" @selected(request('subscription') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="country" class="form-select" aria-label="{{ __('Country') }}">
            <option value="">{{ __('All countries') }}</option>
            @foreach($countries as $code => $name)
                <option value="{{ $code }}" @selected(request('country') === $code)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="limits" class="form-select" aria-label="{{ __('Limits') }}">
            <option value="">{{ __('Any usage') }}</option>
            <option value="reached" @selected(request('limits') === 'reached')>{{ __('At or past a limit') }}</option>
        </select>
    </div>
    <div class="w-40 shrink-0">
        <input type="date" name="from" value="{{ request('from') }}" class="form-input" aria-label="{{ __('Signed up from') }}" title="{{ __('Signed up from') }}">
    </div>
    <div class="w-40 shrink-0">
        <input type="date" name="to" value="{{ request('to') }}" class="form-input" aria-label="{{ __('Signed up to') }}" title="{{ __('Signed up to') }}">
    </div>
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
    </button>
    <a href="{{ route('admin.businesses.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</form>
