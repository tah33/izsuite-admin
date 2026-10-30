{{-- The user's businesses: what each holds, the apps it has on, and its plan, which can be changed here. --}}
<h2 class="font-semibold text-[var(--text-primary)] mb-3">{{ __('Workspaces') }} <span class="font-normal text-[var(--text-muted)]">({{ $totals['businesses'] }})</span></h2>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Workspace') }}</th>
                    <th>{{ __('Apps on') }}</th>
                    <th>{{ __('Plan') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($client->workspaces as $workspace)
                    <tr>
                        <td>
                            <span class="font-semibold text-[var(--text-primary)]">{{ $workspace->name }}</span>
                            <span class="block text-xs text-[var(--text-muted)]">#{{ $workspace->id }} · {{ $workspace->created_at?->format('M d, Y') }}</span>
                            <span class="block text-xs text-[var(--text-muted)] mt-1">
                                {{ trans_choice(':count staff|:count staff', $workspace->staff_count) }}
                                · {{ trans_choice(':count location|:count locations', $workspace->locations_count) }}
                                · {{ trans_choice(':count contact|:count contacts', $workspace->contacts_count) }}
                                · {{ trans_choice(':count item|:count items', $workspace->items_count) }}
                                · {{ \App\Support\Bytes::format((int) $workspace->storage_bytes) }}
                                @if($workspace->package?->storage_limit_mb !== null)
                                    {{ __('of') }} {{ \App\Support\Bytes::format($workspace->package->storage_limit_mb * 1024 * 1024) }}
                                @endif
                            </span>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1 max-w-sm">
                                @forelse($workspace->workspaceApps->sortBy(fn ($row) => $row->app->sort_order) as $row)
                                    <span class="badge {{ $row->app->edition === \App\Enums\Edition::Full ? 'badge-active' : 'badge-trial' }}">{{ $row->app->name }}</span>
                                @empty
                                    <span class="text-xs text-[var(--text-muted)]">{{ __('None') }}</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <form action="{{ route('admin.workspaces.package', $workspace->id) }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <div class="w-40">
                                    <select name="package_id" class="form-select" aria-label="{{ __('Plan for') }} {{ $workspace->name }}">
                                        @foreach($packages as $package)
                                            <option value="{{ $package->id }}" @selected($workspace->package_id === $package->id)>{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-xs btn-secondary" data-loading-label="{{ __('Moving...') }}">{{ __('Move') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-8 text-[var(--text-muted)]">
                            <i data-lucide="building-2" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                            <p>{{ __('This user has no workspace yet.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
