@extends('layouts.admin')
@section('title', __('Edit Feature Flag'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Edit Feature Flag') }}</h1>
        <p class="page-subtitle">{{ $flag->key }} · {{ __('A change counts at once.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.feature-flags.update', $flag->id) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.feature-flags._form')
    </form>
@endsection
