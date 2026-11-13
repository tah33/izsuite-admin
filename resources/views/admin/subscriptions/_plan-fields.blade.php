{{-- The plan and cycle a subscription moves to. Inside admin.partials.reason-modal: $id is the dialog's. Expects $plans and $subscription. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div class="form-group">
        <label for="{{ $id }}-plan" class="form-label">{{ __('Plan') }}</label>
        <select id="{{ $id }}-plan" name="package_id" class="form-select">
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected($plan->id === $subscription->package_id)>{{ $plan->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="{{ $id }}-cycle" class="form-label">{{ __('Cycle') }}</label>
        <select id="{{ $id }}-cycle" name="cycle" class="form-select">
            <option value="monthly" @selected($subscription->cycle === 'monthly')>{{ __('Monthly') }}</option>
            <option value="yearly" @selected($subscription->cycle === 'yearly')>{{ __('Yearly') }}</option>
        </select>
    </div>
</div>
