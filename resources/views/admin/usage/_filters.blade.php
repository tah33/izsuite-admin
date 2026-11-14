{{-- Admin > Usage's filters, in one toolbar row (Doc 15 section 15.18): words, plan, state, the limit it looks at, and the order. --}}
<form action="{{ route('admin.usage.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <div class="search-input-wrapper flex-1 min-w-[220px]">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, BW- ID, or the owner\'s name or e-mail...') }}" class="form-input search-input w-full">
    </div>
    <div class="w-40 shrink-0">
        <select name="package" class="form-select" aria-label="{{ __('Plan') }}">
            <option value="">{{ __('All plans') }}</option>
            @foreach($plans as $key => $name)
                <option value="{{ $key }}" @selected(request('package') === $key)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-52 shrink-0">
        <select name="state" class="form-select" aria-label="{{ __('State') }}">
            <option value="">{{ __('Any state') }}</option>
            @foreach(\App\Services\Admin\UsageService::STATES as $value => $label)
                <option value="{{ $value }}" @selected(request('state') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-36 shrink-0">
        <select name="limit" class="form-select" aria-label="{{ __('Limit') }}">
            <option value="">{{ __('Any limit') }}</option>
            @foreach(\App\Services\Admin\UsageService::LIMITS as $value => $label)
                <option value="{{ $value }}" @selected(request('limit') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-44 shrink-0">
        <select name="sort" class="form-select" aria-label="{{ __('Order') }}">
            @foreach(\App\Services\Admin\UsageService::SORTS as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'closest') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>
    <x-per-page-select />
    <button type="submit" class="btn btn-secondary">
        <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
    </button>
    <a href="{{ route('admin.usage.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
        <i data-lucide="x" class="w-4 h-4"></i>
    </a>

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</form>
