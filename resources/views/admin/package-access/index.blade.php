@extends('layouts.admin')
@section('title', __('Package Access'))

@php($columns = 2 + $packages->count())

@section('content')
    <form id="package-access-form" action="{{ route('admin.package-access.update') }}" method="POST">
        @csrf
        @method('PUT')

        {{-- Stays in view under the topbar while the matrix scrolls; the page's background behind it hides the rows going under. --}}
        <div class="sticky top-[var(--topbar-height)] z-20 -mt-6 pt-6 pb-4 bg-[var(--content-bg)]">
            @include('admin.partials.critical-confirm', ['impact' => __('Saving changes which apps every plan includes, for every business on it.'), 'action' => 'admin.package-access._save'])
        </div>

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
