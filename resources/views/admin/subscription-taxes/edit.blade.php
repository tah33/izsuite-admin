@extends('layouts.admin')
@section('title', __('Edit Subscription Tax'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Edit Subscription Tax') }}</h1>
        <p class="page-subtitle">{{ $tax->name }} {{ $tax->percent() }}% · {{ \App\Support\Countries::name($tax->country) ?? $tax->country }}. {{ __('Invoices already issued keep the tax they had.') }}</p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.subscription-taxes.update', $tax->id) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.subscription-taxes._form')
    </form>
@endsection
