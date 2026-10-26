@extends('layouts.admin')
@section('title', __('Add Menu Item'))

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.menu-items.index') }}" class="text-[var(--primary)]">{{ __('Menu Builder') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Add Item') }}</span>
        </div>
        <h1 class="page-title">{{ __('Add Menu Item') }}</h1>
        <p class="page-subtitle">{{ __('A link in the business sidebar') }}</p>
    </div>

    <form action="{{ route('admin.menu-items.store') }}" method="POST">
        @csrf
        <div class="card mb-6">
            @include('admin.menu-items._form')
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Add Item') }}
            </button>
            <a href="{{ route('admin.menu-items.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
