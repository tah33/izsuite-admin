@extends('layouts.admin')
@section('title', __('Add Staff'))

@section('content')
    {{-- Breadcrumb --}}
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.staff.index') }}" class="text-[var(--primary)]">{{ __('Staff') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __('Add Staff') }}</span>
        </div>
        <h1 class="page-title">{{ __('Add Staff Member') }}</h1>
        <p class="page-subtitle">{{ __('Create a new admin or staff account') }}</p>
    </div>

    <form action="{{ route('admin.staff.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card mb-6">
            @include('admin.staff._form')
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="user-plus" class="w-4 h-4"></i> {{ __('Create Staff') }}
            </button>
            <a href="{{ route('admin.staff.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
