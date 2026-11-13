@extends('layouts.admin')
@section('title', __('Subscriptions'))

{{-- Doc 15 section 15.13: every business's subscription to a paid plan. A business on Billing Starter, with none running, is in Admin > Businesses. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Subscriptions') }}</h1>
        <p class="page-subtitle">{{ __('Businesses\' subscriptions to the paid plans: where each stands, and how it is paid.') }}</p>
    </div>

    @include('admin.subscriptions._filters')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Dates') }}</th>
                        <th>{{ __('Payment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptions as $subscription)
                        @include('admin.subscriptions._row')
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="repeat" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No subscriptions match.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">{{ $subscriptions->links() }}</div>
        @endif
    </div>
@endsection
