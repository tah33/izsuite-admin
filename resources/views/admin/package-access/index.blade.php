@extends('layouts.admin')
@section('title', __('Package Access'))

@php($columns = 2 + $packages->count())

@section('content')
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <p class="text-sm flex-1 min-w-[240px] text-[var(--text-secondary)]">
            {{ __('Tick the lowest plan that includes each app: every plan above it includes it too. Saving is refused while a plan would hold an app without what that app needs. Businesses that already have an app on keep it until their own plan changes.') }}
        </p>
        <button type="submit" form="package-access-form" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Access') }}
        </button>
    </div>

    <form id="package-access-form" action="{{ route('admin.package-access.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table" id="access-matrix">
                    <thead>
                        <tr>
                            <th>{{ __('App') }}</th>
                            <th>{{ __('Edition') }}</th>
                            @foreach($packages as $package)
                                <th class="text-center">{{ $package->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([...$categories->map(fn ($c) => ['name' => $c->name, 'apps' => $c->apps]), ['name' => __('Without a category'), 'apps' => $uncategorised]] as $group)
                            @continue($group['apps']->isEmpty())

                            <tr>
                                <td colspan="{{ $columns }}" class="!py-2 bg-[var(--content-bg)] text-xs font-semibold uppercase tracking-wide text-[var(--text-secondary)]">
                                    {{ $group['name'] }}
                                </td>
                            </tr>

                            @foreach($group['apps'] as $app)
                                <tr data-app-row>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="{{ $app->icon ?: 'app-window' }}" class="w-4 h-4 text-[var(--text-muted)]"></i>
                                            <span class="font-medium text-[var(--text-primary)]">{{ $app->name }}</span>
                                            @if($app->status === 'coming_soon')
                                                <span class="badge badge-warning">{{ __('Coming Soon') }}</span>
                                            @endif
                                            @unless($app->is_active)
                                                <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $app->edition === \App\Enums\Edition::Full ? 'badge-active' : 'badge-trial' }}">{{ $app->editionLabel() }}</span>
                                    </td>
                                    @foreach($packages as $package)
                                        <td class="text-center">
                                            <input type="checkbox"
                                                   name="access[{{ $app->id }}][]"
                                                   value="{{ $package->id }}"
                                                   data-rank="{{ $package->rank }}"
                                                   aria-label="{{ $app->name }} in {{ $package->name }}"
                                                   @checked(in_array($package->id, $access[$app->id] ?? []))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // A higher plan includes everything a lower one does: ticking a plan ticks
    // the ones above it, and unticking one unticks the ones below it.
    document.querySelectorAll('#access-matrix tr[data-app-row]').forEach(function (row) {
        row.addEventListener('change', function (event) {
            var box = event.target;
            if (! box.matches('input[type="checkbox"]')) {
                return;
            }

            var rank = Number(box.dataset.rank);

            row.querySelectorAll('input[type="checkbox"]').forEach(function (other) {
                var otherRank = Number(other.dataset.rank);

                if (box.checked && otherRank > rank) {
                    other.checked = true;
                }

                if (! box.checked && otherRank < rank) {
                    other.checked = false;
                }
            });
        });
    });
</script>
@endpush
