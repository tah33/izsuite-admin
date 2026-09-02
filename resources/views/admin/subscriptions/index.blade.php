@extends('layouts.admin')
@section('title', __('Subscription History'))

@section('content')
    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.subscriptions.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('Subscribe Recruiter') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.subscriptions.index') }}" class="card mb-3">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 items-end">
            <div>
                <label class="form-label text-xs">{{ __('Search') }}</label>
                <div class="search-input-wrapper ![max-width:none]">
                    <i data-lucide="search" class="search-icon"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-input search-input w-full"
                        placeholder="{{ __('User, email, plan, or payment ID') }}"
                    >
                </div>
            </div>

            <div>
                <label class="form-label text-xs">{{ __('Status') }}</label>
                <select name="status" class="form-select w-full">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label text-xs">{{ __('Billing Cycle') }}</label>
                <select name="billing_cycle" class="form-select w-full">
                    <option value="">{{ __('All cycles') }}</option>
                    @foreach($billingCycles as $cycle)
                        <option value="{{ $cycle }}" {{ request('billing_cycle') === $cycle ? 'selected' : '' }}>
                            {{ ucfirst($cycle) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label text-xs">{{ __('From') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input w-full">
            </div>

            <div>
                <label class="form-label text-xs">{{ __('To') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input w-full">
            </div>

            <div class="flex gap-2 items-end">
                <div class="shrink-0">
                    <select name="per_page" class="form-select w-[100px]" onchange="this.form.submit()">
                        @foreach([10, 15, 25, 50, 100] as $option)
                            <option value="{{ $option }}" {{ (int) request('per_page', 15) === $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary flex-1">
                    <i data-lucide="search" class="w-4 h-4 me-1"></i>{{ __('Filter') }}
                </button>
                <a href="{{ route('admin.subscriptions.index') }}" class="btn btn-secondary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Plan') }}</th>
                    <th>{{ __('Payment ID') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Billing') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    @php
                        $statusClasses = [
                            'active' => 'badge-success',
                            'trial' => 'badge-active',
                            'pending' => 'badge-warning',
                            'paused' => 'badge-warning',
                            'cancelled' => 'badge-cancelled',
                        ];
                        $statusClass = $statusClasses[$subscription->status] ?? 'badge-inactive';
                    @endphp
                    <tr>
                        <td class="text-sm text-[var(--text-muted)]">{{ $subscriptions->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">{{ $subscription->user?->name ?? __('Unknown User') }}</span>
                                <span class="text-xs text-[var(--text-muted)]">{{ $subscription->user?->email ?? __('N/A') }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-col">
                                <span class="font-medium text-[var(--text-primary)]">{{ $subscription->name }}</span>
                                <span class="text-xs text-[var(--text-muted)]">{{ $subscription->plan_slug ?: __('custom') }}</span>
                            </div>
                        </td>
                        <td>
                            @if($subscription->payment_id)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-mono text-[var(--text-secondary)]" title="{{ $subscription->payment_id }}">
                                        {{ \Illuminate\Support\Str::limit($subscription->payment_id, 28) }}
                                    </span>
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-secondary copy-payment-id"
                                        data-payment-id="{{ $subscription->payment_id }}"
                                        title="{{ __('Copy Payment ID') }}"
                                    >
                                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            @else
                                <span class="text-xs text-[var(--text-muted)]">{{ __('N/A') }}</span>
                            @endif
                        </td>
                        <td class="text-sm text-[var(--text-primary)]">
                            {{ $subscription->currency }} {{ number_format((float) $subscription->amount, 2) }}
                        </td>
                        <td>
                            <div class="flex flex-col">
                                <span class="text-sm capitalize text-[var(--text-primary)]">{{ $subscription->billing_cycle }}</span>
                                <span class="text-xs text-[var(--text-muted)]">{{ $subscription->payment_method_slug ?: __('N/A') }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $statusClass }}">{{ ucfirst($subscription->status) }}</span>
                        </td>
                        <td class="text-sm text-[var(--text-secondary)]">
                            <div>{{ $subscription->start_date ? $subscription->start_date->format('M d, Y') : __('N/A') }}</div>
                            <div class="text-xs text-[var(--text-muted)]">
                                {{ __('Next:') }} {{ $subscription->next_renewal_date ? $subscription->next_renewal_date->format('M d, Y') : __('N/A') }}
                            </div>
                        </td>
                        <td class="text-sm text-[var(--text-secondary)] whitespace-nowrap">
                            {{ $subscription->created_at->format('M d, Y h:i A') }}
                        </td>
                        <td class="text-end">
                            <div class="flex items-center justify-end gap-2">
                                @if($subscription->latestInvoice)
                                    <a href="{{ route('admin.subscriptions.invoice', $subscription->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Download Invoice') }}">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                    </a>
                                @endif
                                @if($subscription->status === 'active')
                                    <form action="{{ route('admin.subscriptions.pause', $subscription->id) }}" method="POST" onsubmit="return confirm('{{ __('Pause this subscription?') }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs btn-secondary" title="{{ __('Pause') }}">
                                            <i data-lucide="pause" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                @elseif($subscription->status === 'paused')
                                    <form action="{{ route('admin.subscriptions.resume', $subscription->id) }}" method="POST" onsubmit="return confirm('{{ __('Resume this subscription?') }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-xs btn-primary" title="{{ __('Resume') }}">
                                            <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-8 text-[var(--text-muted)]">
                            <i data-lucide="history" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                            <p>{{ __('No subscriptions found for the selected filters.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscriptions->hasPages())
        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    @endif
@endsection

@push('scripts')
<script>
    var copyToastTimer = null;

    function showCopyToast(message, isError) {
        var toast = document.getElementById('copy-payment-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'copy-payment-toast';
            toast.setAttribute('role', 'status');
            toast.style.position = 'fixed';
            toast.style.top = '18px';
            toast.style.right = '18px';
            toast.style.zIndex = '9999';
            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '8px';
            toast.style.fontSize = '13px';
            toast.style.fontWeight = '600';
            toast.style.color = '#ffffff';
            toast.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.18)';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            toast.style.transition = 'opacity 160ms ease, transform 160ms ease';
            toast.style.pointerEvents = 'none';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.style.background = isError ? '#ef4444' : getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#2563EB';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        if (copyToastTimer) {
            clearTimeout(copyToastTimer);
        }

        copyToastTimer = setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
        }, 1400);
    }

    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'absolute';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    document.addEventListener('click', async function (event) {
        var button = event.target.closest('.copy-payment-id');
        if (!button) return;

        var paymentId = button.getAttribute('data-payment-id');
        if (!paymentId) return;

        var originalTitle = button.getAttribute('title') || '';

        try {
            await copyToClipboard(paymentId);
            button.setAttribute('title', '{{ __('Copied') }}');
            showCopyToast('{{ __('Copied payment ID') }}', false);
        } catch (error) {
            button.setAttribute('title', '{{ __('Copy failed') }}');
            showCopyToast('{{ __('Copy failed') }}', true);
        }

        setTimeout(function () {
            button.setAttribute('title', originalTitle);
        }, 1200);
    });
</script>
@endpush
