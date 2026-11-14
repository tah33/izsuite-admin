@extends('layouts.admin')
@section('title', __('Edit Announcement'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Edit Announcement') }}</h1>
        <p class="page-subtitle">{{ __('Saved changes show at once. Those who dismissed it do not see it again.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.announcements.update', $announcement->id) }}" method="POST">
        @csrf
        @method('PUT')
        @include('admin.announcements._form')
    </form>
@endsection
