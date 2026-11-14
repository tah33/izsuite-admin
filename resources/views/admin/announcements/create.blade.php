@extends('layouts.admin')
@section('title', __('New Announcement'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('New Announcement') }}</h1>
        <p class="page-subtitle">{{ __('A banner above every page for the audience chosen, between its days.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.announcements.store') }}" method="POST">
        @csrf
        @include('admin.announcements._form')
    </form>
@endsection
