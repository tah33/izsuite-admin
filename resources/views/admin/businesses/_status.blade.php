{{-- A business's status and every change to it (Doc 15 section 15.6): why, what reactivates it, notes, the day, who, and whether the owner was told. --}}
@php($current = $statusHistory->first())
<div class="card p-6 mb-6">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Status') }}</h2>
        @include('admin.businesses._status-badge', ['status' => $business->status])
    </div>

    @if($current && $current->status === ($business->status ?: 'active'))
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-5">
            <div><dt class="text-[var(--text-muted)]">{{ __('Why (the owner sees it)') }}</dt><dd class="font-medium">{{ $current->reason }}</dd></div>
            @if($current->reactivation)
                <div><dt class="text-[var(--text-muted)]">{{ __('What reactivates it') }}</dt><dd class="font-medium">{{ $current->reactivation }}</dd></div>
            @endif
            @if($current->notes)
                <div><dt class="text-[var(--text-muted)]">{{ __('Internal notes') }}</dt><dd class="font-medium whitespace-pre-line">{{ $current->notes }}</dd></div>
            @endif
            @if($current->closes_on)
                <div><dt class="text-[var(--text-muted)]">{{ __('Closes on') }}</dt><dd class="font-medium">{{ $current->closes_on->format('M d, Y') }}</dd></div>
            @endif
        </dl>
    @else
        <p class="text-sm text-[var(--text-muted)] mb-5">{{ __('Active since it signed up: nobody has changed its status.') }}</p>
    @endif

    @if($statusHistory->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Why') }}</th>
                        <th>{{ __('By') }}</th>
                        <th>{{ __('Owner told') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statusHistory as $change)
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ $change->created_at->format('M d, Y H:i') }}</td>
                            <td class="whitespace-nowrap">
                                @include('admin.businesses._status-badge', ['status' => $change->status])
                                @if($change->closes_on)
                                    <span class="block text-xs text-[var(--text-muted)] mt-1">{{ __('Closes :date', ['date' => $change->closes_on->format('M d, Y')]) }}</span>
                                @endif
                            </td>
                            <td class="text-sm">{{ $change->reason }}</td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $change->admin_name ?? __('izSuite') }}</td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $change->owner_notified ? __('Yes') : __('No') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
