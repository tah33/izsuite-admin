@extends('layouts.admin')
@section('title', __('Edit Promotion'))

@section('content')
    <div class="mb-6">
        <h1 class="page-title">{{ __('Edit Promotion') }}</h1>
        <p class="page-subtitle">
            {{ $promotion->code }} · {{ $promotion->describe() }} · {{ trans_choice('Taken :count time|Taken :count times', $uses) }}.
            {{ __('Invoices it took something off keep their discount and the code as it was.') }}
        </p>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('admin.promotions.update', $promotion->id) }}" method="POST">
        @csrf @method('PUT')
        @include('admin.promotions._form')
    </form>
@endsection
