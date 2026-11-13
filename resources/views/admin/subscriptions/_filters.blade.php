{{-- Admin > Subscriptions' filters (Doc 15 section 15.13), in one toolbar row. .form-select is full width, so each sits in its own sized box. --}}
<form action="{{ route('admin.subscriptions.index') }}" method="GET" class="flex flex-wrap items-center gap-2 mb-4">
    <div class="search-input-wrapper flex-1 min-w-[220px]">
        <i data-lucide="search" class="search-icon"></i>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Business, BW- ID, or the owner...') }}" class="form-input search-input w-full">
    </div>
    <div class="w-48 shrink-0">
        <select name="status" class="form-select" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Any status') }}</option>
            @foreach(\App\Support\SubscriptionStatus::LABELS as $value => $label)
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
    <div class="w-36 shrink-0">
        <select name="cycle" class="form-select" aria-label="{{ __('Cycle') }}">
            <option value="">{{ __('Any cycle') }}</option>
            <option value="monthly" @selected(request('cycle') === 'monthly')>{{ __('Monthly') }}</option>
            <option value="yearly" @selected(request('cycle') === 'yearly')>{{ __('Yearly') }}</option>
        </select>
    </div>
    <div class="w-40 shrink-0">
        <select name="provider" class="form-select" aria-label="{{ __('Paid by') }}">
            <option value="">{{ __('Paid by any') }}</option>
            <option value="card" @selected(request('provider') === 'card')>{{ __('Card saved') }}</option>
            <option value="transfer" @selected(request('provider') === 'transfer')>{{ __('Bank transfer') }}</option>
        </select>
    </div>
    <x-per-page-select />
    <button type="submit" class="btn btn-secondary"><i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}</button>
    <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}"><i data-lucide="x" class="w-4 h-4"></i></a>

    @if($errors->any())
        <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
    @endif
</form>
