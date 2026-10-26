{{-- Subscription History: the toolbar - filters on the left, page actions on the right --}}
<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
        <div class="search-input-wrapper flex-1 min-w-[220px]">
            <i data-lucide="search" class="search-icon"></i>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                class="form-input search-input w-full"
                placeholder="{{ __('User, email, plan, or payment ID') }}"
            >
        </div>

        <div class="w-40 shrink-0">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="w-36 shrink-0">
            <select name="billing_cycle" class="form-select" aria-label="{{ __('Billing Cycle') }}">
                <option value="">{{ __('All cycles') }}</option>
                @foreach($billingCycles as $cycle)
                    <option value="{{ $cycle }}" {{ request('billing_cycle') === $cycle ? 'selected' : '' }}>
                        {{ ucfirst($cycle) }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- One group, so the range wraps as a unit instead of splitting From/To across lines. --}}
        <div class="flex items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
                {{ __('From') }}
                <span class="w-[165px]"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input"></span>
            </label>

            <label class="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
                {{ __('To') }}
                <span class="w-[165px]"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input"></span>
            </label>
        </div>

        <x-per-page-select />
        <button type="submit" class="btn btn-secondary">
            <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
        </button>
        <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
            <i data-lucide="x" class="w-4 h-4"></i>
        </a>
    </form>

    <a href="{{ route('admin.subscriptions.create') }}" class="btn btn-primary">
        <i data-lucide="plus" class="w-4 h-4"></i>
        {{ __('Subscribe Recruiter') }}
    </a>
</div>
