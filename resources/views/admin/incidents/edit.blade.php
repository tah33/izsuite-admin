@extends('layouts.admin')
@section('title', __('Edit Incident'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Edit Incident') }}</h1>
        <p class="page-subtitle">{{ __('What the incident is. Its status and who is responsible change by a note on its page.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.incidents.update', $incident->id) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.incidents._form')
    </form>
@endsection
