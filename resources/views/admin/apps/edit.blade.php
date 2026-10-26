@extends('layouts.admin')
@section('title', __('Edit App') . ' — ' . $app->name)

@section('content')
    {{-- Breadcrumb --}}
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.apps.index') }}" class="text-[var(--primary)]">{{ __('Apps') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $app->name }}</span>
        </div>
        <h1 class="page-title">{{ __('Edit App') }}</h1>
        <p class="page-subtitle">
            {{ $app->app_key }} · {{ $app->editionLabel() }} {{ __('edition of') }} {{ $app->family_key }}
            @if($app->packages->isNotEmpty())
                · {{ __('in') }} {{ $app->packages->sortBy('rank')->pluck('name')->implode(', ') }}
            @else
                · {{ __('in no plan yet') }}
            @endif
        </p>
    </div>

    <form action="{{ route('admin.apps.update', $app->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="card mb-6">
            @include('admin.apps._form')
        </div>

        <div class="flex items-center gap-3 mb-8">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Changes') }}
            </button>
            <a href="{{ route('admin.apps.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>

    {{-- Dependencies: what this app needs turned on first --}}
    <div class="card">
        <h2 class="section-title mb-1">{{ __('Requires') }}</h2>
        <p class="text-sm mb-4 text-[var(--text-secondary)]">
            {{ __('Apps a business must have on before it can turn this one on, and cannot turn off while this one is on. A Lite requirement is met by either edition.') }}
        </p>

        @if($app->dependencies->isEmpty())
            <p class="text-sm mb-4 text-[var(--text-muted)]">{{ __('Nothing - this app can be turned on by itself.') }}</p>
        @else
            <div class="table-container mb-4">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Required app') }}</th>
                            <th>{{ __('At least') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($app->dependencies as $dependency)
                            <tr>
                                <td>
                                    <span class="font-semibold">{{ $families[$dependency->requires_family] ?? $dependency->requires_family }}</span>
                                    <span class="block text-xs text-[var(--text-muted)]">{{ $dependency->requires_family }}</span>
                                </td>
                                <td><span class="badge badge-info">{{ $dependency->min_edition->label() }}</span></td>
                                <td class="text-end">
                                    <form action="{{ route('admin.apps.dependencies.destroy', [$app->id, $dependency->id]) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-secondary" data-loading-label="{{ __('Removing...') }}">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i> {{ __('Remove') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <form action="{{ route('admin.apps.dependencies.store', $app->id) }}" method="POST" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[220px]">
                <label class="form-label" for="requires_family">{{ __('Add a requirement') }}</label>
                <select id="requires_family" name="requires_family" class="form-input" required>
                    <option value="">{{ __('Choose an app') }}</option>
                    @foreach($families as $familyKey => $familyName)
                        <option value="{{ $familyKey }}">{{ $familyName }} ({{ $familyKey }})</option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="form-label" for="min_edition">{{ __('At least') }}</label>
                <select id="min_edition" name="min_edition" class="form-input">
                    @foreach(\App\Enums\Edition::options() as $value => $label)
                        <option value="{{ $value }}">{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add') }}
            </button>
        </form>
        <p class="text-xs mt-3 text-[var(--text-muted)]">
            {{ __('Refused if it is the app\'s own family, if it would go round in a circle, or if the required app is only in a higher plan than this one.') }}
        </p>
    </div>
@endsection
