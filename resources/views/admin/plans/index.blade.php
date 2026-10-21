@extends('layouts.admin')
@section('title', __('Plans & Pricing'))

@section('content')
    {{-- Flash --}}
    @if(session('error'))
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ session('error') }}
        </div>
    @endif

    {{-- One toolbar row: price toggle on the left, page actions on the right. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        {{-- Monthly / Yearly Toggle --}}
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-[var(--text-primary)]">{{ __('Show prices:') }}</span>
            <div class="inline-flex rounded-lg overflow-hidden border border-[var(--card-border)]" id="billing-toggle">
                {{-- Active/hover colors come from .billing-tab in app.css; it is
                     unlayered, so color utilities on these buttons would lose. --}}
                <button type="button" class="billing-tab active px-4 py-1.5 text-sm font-medium transition-all" data-interval="monthly">{{ __('Monthly') }}</button>
                <button type="button" class="billing-tab px-4 py-1.5 text-sm font-medium transition-all" data-interval="yearly">{{ __('Yearly') }}</button>
            </div>
        </div>

        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary ms-auto">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New Plan') }}
        </a>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Audience') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="price-col" data-interval="monthly">{{ __('Monthly Price') }}</th>
                        <th class="price-col hidden" data-interval="yearly">{{ __('Yearly Price') }}</th>
                        <th>{{ __('Trial') }}</th>
                        <th>{{ __('Users') }}</th>
                        <th>{{ __('Limits') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td class="text-[var(--text-muted)]">{{ $loop->iteration + ($plans->currentPage() - 1) * $plans->perPage() }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-[var(--text-primary)]">{{ $plan->name }}</span>
                                    @if($plan->is_featured)
                                        <span class="badge badge-warning text-xs">{{ __('Featured') }}</span>
                                    @endif
                                </div>
                                @if($plan->description)
                                    <p class="text-xs mt-0.5 text-[var(--text-muted)]">{{ Str::limit($plan->description, 60) }}</p>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ ucfirst($plan->plan_for ?? 'recruiter') }}</span>
                            </td>
                            <td>
                                @if(($plan->billing_type ?? 'monthly') === 'yearly')
                                    <span class="badge badge-warning">{{ __('Yearly') }}</span>
                                @else
                                    <span class="badge badge-info">{{ __('Monthly') }}</span>
                                @endif
                            </td>
                            <td class="price-col font-semibold text-[var(--primary)]" data-interval="monthly">
                                {{ format_price($plan->monthly_price) }}
                            </td>
                            <td class="price-col hidden font-semibold text-[var(--primary)]" data-interval="yearly">
                                {{ format_price($plan->yearly_price) }}
                            </td>
                            <td>
                                @if($plan->trial_days > 0)
                                    <span class="text-sm">{{ $plan->trial_days }} {{ __('days') }}</span>
                                @else
                                    <span class="text-[var(--text-muted)]">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $plan->subscriptions_count }}</span>
                            </td>
                            <td class="text-xs text-[var(--text-muted)]">
                                {{ __('Jobs') }}: {{ $plan->job_postings_limit ?? __('Unlimited') }}<br>
                                {{ __('AI') }}: {{ $plan->ai_screenings_limit ?? __('Unlimited') }}<br>
                                {{ __('Team') }}: {{ $plan->team_members_limit ?? __('Unlimited') }}
                            </td>
                            <td>
                                @if($plan->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-plan-{{ $plan->id }}')">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <a href="{{ route('admin.plans.edit', $plan->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>

                                    <x-confirm-modal
                                        id="delete-plan-{{ $plan->id }}"
                                        :title="__('Delete Plan?')"
                                        :message="__('Are you sure you want to delete this plan? This action cannot be undone.')"
                                        :action="route('admin.plans.destroy', $plan->id)"
                                        method="DELETE"
                                        :confirm-text="__('Delete')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="tag" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No plans yet. Create your first plan.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($plans->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $plans->links() }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.billing-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            const interval = btn.dataset.interval;
            document.querySelectorAll('.billing-tab').forEach(b => {
                b.classList.toggle('active', b === btn);
            });
            document.querySelectorAll('.price-col').forEach(col => {
                col.classList.toggle('hidden', col.dataset.interval !== interval);
            });
        });
    });
</script>
@endpush
