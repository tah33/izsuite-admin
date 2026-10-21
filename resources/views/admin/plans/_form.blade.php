{{-- Shared form fields for create/edit plan --}}

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Name --}}
    <div class="form-group">
        <label for="name" class="form-label">{{ __('Plan Name') }} <span class="text-red-400">*</span></label>
        <input type="text" name="name" id="name" class="form-input" value="{{ old('name', $plan->name ?? '') }}" required>
        @error('name') <span class="form-error">{{ $message }}</span> @enderror
    </div>

    {{-- Sort Order --}}
    <div class="form-group">
        <label for="sort_order" class="form-label">{{ __('Sort Order') }}</label>
        <input type="number" name="sort_order" id="sort_order" class="form-input" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0">
    </div>

    <div class="form-group">
        <label for="plan_for" class="form-label">{{ __('Plan For') }} <span class="text-red-400">*</span></label>
        <select name="plan_for" id="plan_for" class="form-input" required>
            <option value="recruiter" {{ old('plan_for', $plan->plan_for ?? 'recruiter') === 'recruiter' ? 'selected' : '' }}>{{ __('Recruiter') }}</option>
            <option value="candidate" {{ old('plan_for', $plan->plan_for ?? 'recruiter') === 'candidate' ? 'selected' : '' }}>{{ __('Candidate') }}</option>
        </select>
    </div>

    <div class="form-group">
        <label for="billing_type" class="form-label">{{ __('Type') }} <span class="text-red-400">*</span></label>
        @php
            $selectedBillingType = old('billing_type', $plan->billing_type ?? 'monthly');
        @endphp
        <select name="billing_type" id="billing_type" class="form-input" required>
            <option value="monthly" {{ $selectedBillingType === 'monthly' ? 'selected' : '' }}>{{ __('Monthly') }}</option>
            <option value="yearly" {{ $selectedBillingType === 'yearly' ? 'selected' : '' }}>{{ __('Yearly') }}</option>
        </select>
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Usage resets by this billing cycle unless the plan price is 0.') }}</p>
    </div>
</div>

{{-- Description --}}
<div class="form-group">
    <label for="description" class="form-label">{{ __('Description') }}</label>
    <textarea name="description" id="description" class="form-input" rows="2">{{ old('description', $plan->description ?? '') }}</textarea>
    @error('description') <span class="form-error">{{ $message }}</span> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    {{-- Monthly Price --}}
    <div class="form-group">
        <label for="monthly_price" class="form-label">{{ __('Monthly Price') }}</label>
        <input type="number" name="monthly_price" id="monthly_price" class="form-input" step="0.01" min="0" value="{{ old('monthly_price', $plan->monthly_price ?? '0.00') }}">
    </div>

    {{-- Yearly Price --}}
    <div class="form-group">
        <label for="yearly_price" class="form-label">{{ __('Yearly Price') }}</label>
        <input type="number" name="yearly_price" id="yearly_price" class="form-input" step="0.01" min="0" value="{{ old('yearly_price', $plan->yearly_price ?? '0.00') }}">
    </div>

    {{-- Trial Days --}}
    <div class="form-group">
        <label for="trial_days" class="form-label">{{ __('Trial Days') }}</label>
        <input type="number" name="trial_days" id="trial_days" class="form-input" min="0" value="{{ old('trial_days', $plan->trial_days ?? 0) }}">
    </div>
</div>

<script>
    (() => {
        const billingType = document.getElementById('billing_type');
        const monthlyPrice = document.getElementById('monthly_price');
        const yearlyPrice = document.getElementById('yearly_price');

        if (!billingType || !monthlyPrice || !yearlyPrice) {
            return;
        }

        const syncRequiredPrice = () => {
            monthlyPrice.required = billingType.value === 'monthly';
            yearlyPrice.required = billingType.value === 'yearly';
        };

        // jQuery, not addEventListener: the select is a Select2, which
        // announces a pick through jQuery.
        $(billingType).on('change', syncRequiredPrice);
        syncRequiredPrice();
    })();
</script>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="form-group">
        <label for="job_postings_limit" class="form-label">{{ __('Job Postings Limit') }}</label>
        <input type="number" name="job_postings_limit" id="job_postings_limit" class="form-input" min="0" value="{{ old('job_postings_limit', $plan->job_postings_limit ?? '') }}" placeholder="{{ __('Leave empty for unlimited') }}">
    </div>
    <div class="form-group">
        <label for="ai_screenings_limit" class="form-label">{{ __('AI Screenings Limit') }}</label>
        <input type="number" name="ai_screenings_limit" id="ai_screenings_limit" class="form-input" min="0" value="{{ old('ai_screenings_limit', $plan->ai_screenings_limit ?? '') }}" placeholder="{{ __('Leave empty for unlimited') }}">
    </div>
    <div class="form-group">
        <label for="team_members_limit" class="form-label">{{ __('Team Members Limit') }}</label>
        <input type="number" name="team_members_limit" id="team_members_limit" class="form-input" min="0" value="{{ old('team_members_limit', $plan->team_members_limit ?? '') }}" placeholder="{{ __('Leave empty for unlimited') }}">
    </div>
</div>

{{-- Features (one per line or comma-separated) --}}
<div class="form-group">
    <label for="features" class="form-label">{{ __('Features') }} <span class="text-xs text-[var(--text-muted)]">({{ __('one per line') }})</span></label>
    <textarea name="features" id="features" class="form-input" rows="4" placeholder="{{ __('Unlimited subscriptions') }}&#10;{{ __('Email alerts') }}&#10;{{ __('Priority support') }}">{{ old('features', isset($plan) && $plan->features ? implode("\n", $plan->features) : '') }}</textarea>
</div>

<div class="flex items-center gap-6">
    {{-- Active --}}
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_active" value="1" class="form-checkbox" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
        <span class="text-sm text-[var(--text-primary)]">{{ __('Active') }}</span>
    </label>

    {{-- Featured --}}
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="is_featured" value="1" class="form-checkbox" {{ old('is_featured', $plan->is_featured ?? false) ? 'checked' : '' }}>
        <span class="text-sm text-[var(--text-primary)]">{{ __('Featured') }}</span>
    </label>
</div>

{{-- Payment Provider IDs --}}
<div class="mt-6">
    <h3 class="text-sm font-semibold mb-3 text-[var(--text-primary)]">{{ __('Payment Provider IDs') }}</h3>
    <p class="text-xs mb-3 text-[var(--text-muted)]">{{ __('Enter your payment gateway price/plan IDs for each billing interval.') }}</p>

    <div class="overflow-x-auto">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>{{ __('Provider') }}</th>
                    <th>{{ __('Monthly Price ID') }}</th>
                    <th>{{ __('Yearly Price ID') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $providerList = collect($onlineGateways ?? [])->pluck('slug')->values()->all();
                    $existing = isset($plan) ? $plan->paymentProviders->groupBy('provider') : collect();
                @endphp
                @foreach($providerList as $i => $provider)
                    @php
                        $monthlyId = $existing->get($provider)?->firstWhere('interval', 'monthly')?->provider_price_id ?? '';
                        $yearlyId  = $existing->get($provider)?->firstWhere('interval', 'yearly')?->provider_price_id ?? '';
                    @endphp
                    <tr>
                        <td class="font-medium capitalize text-[var(--text-primary)]">{{ ucfirst($provider) }}</td>
                        <td>
                            <input type="hidden" name="providers[{{ $i * 2 }}][provider]" value="{{ $provider }}">
                            <input type="hidden" name="providers[{{ $i * 2 }}][interval]" value="monthly">
                            <input type="text" name="providers[{{ $i * 2 }}][provider_price_id]" class="form-input text-xs" placeholder="e.g. price_xxx" value="{{ old("providers.{$i}.monthly", $monthlyId) }}">
                        </td>
                        <td>
                            <input type="hidden" name="providers[{{ $i * 2 + 1 }}][provider]" value="{{ $provider }}">
                            <input type="hidden" name="providers[{{ $i * 2 + 1 }}][interval]" value="yearly">
                            <input type="text" name="providers[{{ $i * 2 + 1 }}][provider_price_id]" class="form-input text-xs" placeholder="e.g. price_xxx" value="{{ old("providers.{$i}.yearly", $yearlyId) }}">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
