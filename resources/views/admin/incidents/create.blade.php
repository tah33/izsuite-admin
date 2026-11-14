@extends('layouts.admin')
@section('title', __('Open Incident'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Open Incident') }}</h1>
        <p class="page-subtitle">{{ __('Something wrong with a service. It opens with this summary as the first note on its timeline.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.incidents.store') }}" method="POST">
        @csrf
        @include('admin.incidents._form', ['incident' => null])
    </form>
@endsection
