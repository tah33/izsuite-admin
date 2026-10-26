{{-- Subscription History: one subscription's row --}}
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
