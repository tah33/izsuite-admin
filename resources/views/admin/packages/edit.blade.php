@extends('layouts.admin')
@section('title', __('Edit Plan') . ' — ' . $package->name)

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.packages.index') }}" class="text-[var(--primary)]">{{ __('Packages') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ $package->name }}</span>
        </div>
        <h1 class="page-title">{{ __('Edit Plan') }}</h1>
        <p class="page-subtitle">
            <code>{{ $package->key }}</code> · {{ __('rank') }} {{ $package->rank }} ·
            {{ __('The key and rank are fixed: the code relies on them.') }}
        </p>
    </div>

    <form action="{{ route('admin.packages.update', $package->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card mb-6">
            <div class="mb-4">
                <label class="form-label" for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name', $package->name) }}" class="form-input" required>
                @error('name')
                    <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label" for="description">{{ __('Description') }}</label>
                <textarea id="description" name="description" rows="3" class="form-input">{{ old('description', $package->description) }}</textarea>
                @error('description')
                    <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" class="toggle-switch" @checked(old('is_active', $package->is_active))>
                <span class="form-label mb-0">{{ __('Offered') }}</span>
            </label>
            <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Businesses already on a plan keep it even when it is no longer offered.') }}</p>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Changes') }}
            </button>
            <a href="{{ route('admin.packages.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
