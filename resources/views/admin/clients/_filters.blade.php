{{-- Admin > Users' filters (Doc 15 section 15.7), in one toolbar row. .form-select is full width, so each sits in its own sized box. --}}
<form action="{{ route('admin.clients.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <div class="search-input-wrapper flex-1 min-w-[220px]">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, email, phone or business...') }}" class="form-input search-input w-full">
    </div>
    <div class="w-40 shrink-0">
        <select name="package" class="form-select" aria-label="{{ __('Plan') }}">
            <option value="">{{ __('All plans') }}</option>
            @foreach($packages as $package)
                <option value="{{ $package->key }}" @selected(request('package') === $package->key)>{{ $package->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-36 shrink-0">
        <select name="status" class="form-select" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Any status') }}</option>
            @foreach(['active' => __('Active'), 'locked' => __('Locked'), 'closed' => __('Closed'), 'inactive' => __('Inactive')] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-44 shrink-0">
        <select name="verified" class="form-select" aria-label="{{ __('Email') }}">
            <option value="">{{ __('Any email') }}</option>
            <option value="yes" @selected(request('verified') === 'yes')>{{ __('Email verified') }}</option>
            <option value="no" @selected(request('verified') === 'no')>{{ __('Email not verified') }}</option>
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="two_factor" class="form-select" aria-label="{{ __('Two-factor') }}">
            <option value="">{{ __('Any two-factor') }}</option>
            <option value="on" @selected(request('two_factor') === 'on')>{{ __('Two-factor on') }}</option>
            <option value="off" @selected(request('two_factor') === 'off')>{{ __('Two-factor off') }}</option>
        </select>
    </div>
    <div class="w-48 shrink-0">
        <select name="alerts" class="form-select" aria-label="{{ __('Security alerts') }}">
            <option value="">{{ __('Any alerts') }}</option>
            <option value="yes" @selected(request('alerts') === 'yes')>{{ __('With security alerts') }}</option>
        </select>
    </div>
    <x-per-page-select />
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
    </button>
    <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</form>
