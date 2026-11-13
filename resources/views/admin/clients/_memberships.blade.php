{{-- The businesses the user is in (Doc 15 section 15.7), their own among them: their role and place in each. --}}
<div class="card overflow-hidden mb-6">
    <div class="p-6 pb-3">
        <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Businesses They Are In') }} <span class="font-normal text-[var(--text-muted)]">({{ $client->memberships->count() }})</span></h2>
    </div>

    @if($client->memberships->isEmpty())
        <p class="px-6 pb-6 text-sm text-[var(--text-muted)]">{{ __('None.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Their Place') }}</th>
                        <th>{{ __('Since') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($client->memberships as $membership)
                        @php($business = $membership->workspace)
                        <tr>
                            <td>
                                @if($business && $may('admin.businesses.show'))
                                    <a href="{{ route('admin.businesses.show', $business->id) }}" class="font-semibold text-[var(--primary)]">{{ $business->name }}</a>
                                @else
                                    <span class="font-semibold text-[var(--text-primary)]">{{ $business?->name ?? __('A deleted business') }}</span>
                                @endif
                                @if($business && $business->user_id === $client->id)
                                    <span class="badge badge-active ms-1">{{ __('Owner') }}</span>
                                @endif
                                @if($business && $business->status !== \App\Support\BusinessStatus::ACTIVE)
                                    @include('admin.businesses._status-badge', ['status' => $business->status])
                                @endif
                            </td>
                            <td class="text-sm">{{ $membership->role?->name ?? '—' }}</td>
                            <td class="text-sm">{{ [
                                \App\Models\User\WorkspaceMember::PENDING   => __('Invited'),
                                \App\Models\User\WorkspaceMember::ACTIVE    => __('Active'),
                                \App\Models\User\WorkspaceMember::SUSPENDED => __('Suspended'),
                            ][$membership->status] ?? $membership->status }}</td>
                            <td class="text-sm text-[var(--text-muted)] whitespace-nowrap">{{ $membership->created_at?->format('M d, Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
