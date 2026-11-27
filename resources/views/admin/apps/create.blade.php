@extends('layouts.admin')
@section('title', __('Add App'))

@section('content')
    {{-- Breadcrumb --}}
    <div class="mb-4">
        <div class="flex items-center gap-2 text-sm mb-1 text-[var(--text-muted)]">
            <a href="{{ route('admin.apps.index') }}" class="text-[var(--primary)]">{{ __('Apps') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Add App') }}</span>
        </div>
        <h1 class="page-title">{{ __('Add New App') }}</h1>
        <p class="page-subtitle">{{ __('Create a new application entry') }}</p>
    </div>

    <form action="{{ route('admin.apps.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card mb-6">
            @include('admin.apps._form')
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Create App') }}
            </button>
            <a href="{{ route('admin.apps.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
