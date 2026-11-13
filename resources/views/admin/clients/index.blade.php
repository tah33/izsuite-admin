@extends('layouts.admin')
@section('title', __('Users'))

{{-- Doc 15 section 15.7: every account that signed up on the website, with its sign-in checks and security alerts. --}}
@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('Users') }}</h1>
        <p class="page-subtitle">{{ __('Everyone with an izSuite account: their businesses, sign-in checks and security alerts.') }}</p>
    </div>

    @include('admin.clients._filters')

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Businesses') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Security Alerts') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        @include('admin.clients._row')
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No users found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
@endsection
