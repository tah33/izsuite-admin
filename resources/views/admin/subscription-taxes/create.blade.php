@extends('layouts.admin')
@section('title', __('New Subscription Tax'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('New Subscription Tax') }}</h1>
        <p class="page-subtitle">{{ __('The tax added to subscription invoices for businesses in a country') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.subscription-taxes.store') }}" method="POST">
        @csrf
        @include('admin.subscription-taxes._form')
    </form>
@endsection
