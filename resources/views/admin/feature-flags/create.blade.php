@extends('layouts.admin')
@section('title', __('New Feature Flag'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('New Feature Flag') }}</h1>
        <p class="page-subtitle">{{ __('A feature the code asks about by its key, on for the audience chosen.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.feature-flags.store') }}" method="POST">
        @csrf
        @include('admin.feature-flags._form')
    </form>
@endsection
