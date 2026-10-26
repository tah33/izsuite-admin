@extends('layouts.admin')
@section('title', __('Edit Menu Item') . ' — ' . $item->label)

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.menu-items.index') }}" class="text-[var(--primary)]">{{ __('Menu Builder') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $item->label }}</span>
        </div>
        <h1 class="page-title">{{ __('Edit Menu Item') }}</h1>
        <p class="page-subtitle">{{ __('Changes reach every business on its next page load') }}</p>
    </div>

    <form action="{{ route('admin.menu-items.update', $item->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card mb-6">
            @include('admin.menu-items._form')
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Changes') }}
            </button>
            <a href="{{ route('admin.menu-items.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
