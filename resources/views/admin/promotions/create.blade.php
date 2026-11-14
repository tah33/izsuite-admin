@extends('layouts.admin')
@section('title', __('New Promotion'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('New Promotion') }}</h1>
        <p class="page-subtitle">{{ __('A code a business types when it subscribes, for a discount off its first invoice') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.promotions.store') }}" method="POST">
        @csrf
        @include('admin.promotions._form')
    </form>
@endsection
