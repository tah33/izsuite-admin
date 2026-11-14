{{-- A business's usage history (Doc 15 section 15.18): its daily snapshots in the chosen days as a chart, and the days its plan or usage changed. --}}
<div class="card mb-6">
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <h3 class="section-title">{{ __('History') }}</h3>
        <form action="{{ route('admin.usage.show', $business->id) }}" method="GET" class="flex flex-wrap items-center gap-2 ms-auto">
            <div class="w-40">
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-input" aria-label="{{ __('From') }}" title="{{ __('From') }}">
            </div>
            <div class="w-40">
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-input" aria-label="{{ __('To') }}" title="{{ __('To') }}">
            </div>
            <button type="submit" class="btn btn-secondary">{{ __('Show') }}</button>
        </form>
        @if($errors->any())
            <p class="basis-full text-sm text-[var(--danger)]">{{ $errors->first() }}</p>
        @endif
    </div>

    @if($chart['labels'] === [])
        <p class="text-sm text-[var(--text-muted)]">{{ __('No snapshot in these days yet. One is kept for each business every day at 23:50 UTC.') }}</p>
    @else
        <div class="h-[260px] relative w-full overflow-hidden mb-6">
            <canvas id="usage-history-chart" aria-label="{{ __('Usage history') }}" role="img"></canvas>
        </div>

        <h4 class="text-sm font-semibold text-[var(--text-primary)] mb-2">{{ __('Days It Changed') }}</h4>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Day') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th class="text-end">{{ __('Users') }}</th>
                        <th class="text-end">{{ __('Locations') }}</th>
                        <th class="text-end">{{ __('Storage') }}</th>
                        <th class="text-end">{{ __('Apps on') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($changes as $day)
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ $day->day->format('M d, Y') }}</td>
                            <td class="text-sm">{{ $day->package?->name ?? __('No plan') }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $day->users }}{{ $day->user_limit !== null ? ' / '.$day->user_limit : '' }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ $day->locations }}{{ $day->location_limit !== null ? ' / '.$day->location_limit : '' }}</td>
                            <td class="text-sm text-end whitespace-nowrap">{{ \App\Support\Bytes::format($day->storage_bytes) }}{{ $day->storage_limit_mb !== null ? ' / '.\App\Support\Bytes::format($day->storage_limit_mb * 1048576) : '' }}</td>
                            <td class="text-sm text-end">{{ $day->apps }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
