@extends('layouts.admin')
@section('title', __('Subscription History'))

@section('content')
    {{-- One toolbar row: filters on the left, page actions on the right. --}}
    @include('admin.subscriptions._filters')

    <div class="card overflow-x-auto">
        <table class="data-table w-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Plan') }}</th>
                    <th>{{ __('Payment ID') }}</th>
                    <th>{{ __('Amount') }}</th>
                    <th>{{ __('Billing') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    @include('admin.subscriptions._row')
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-8 text-[var(--text-muted)]">
                            <i data-lucide="history" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                            <p>{{ __('No subscriptions found for the selected filters.') }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscriptions->hasPages())
        <div class="mt-4">
            {{ $subscriptions->links() }}
        </div>
    @endif
@endsection

@push('scripts')
<script>
    var copyToastTimer = null;

    function showCopyToast(message, isError) {
        var toast = document.getElementById('copy-payment-toast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'copy-payment-toast';
            toast.setAttribute('role', 'status');
            toast.style.position = 'fixed';
            toast.style.top = '18px';
            toast.style.right = '18px';
            toast.style.zIndex = '9999';
            toast.style.padding = '10px 14px';
            toast.style.borderRadius = '8px';
            toast.style.fontSize = '13px';
            toast.style.fontWeight = '600';
            toast.style.color = '#ffffff';
            toast.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.18)';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
            toast.style.transition = 'opacity 160ms ease, transform 160ms ease';
            toast.style.pointerEvents = 'none';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.style.background = isError ? '#ef4444' : getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#1769E8';
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        if (copyToastTimer) {
            clearTimeout(copyToastTimer);
        }

        copyToastTimer = setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-6px)';
        }, 1400);
    }

    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'absolute';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    document.addEventListener('click', async function (event) {
        var button = event.target.closest('.copy-payment-id');
        if (!button) return;

        var paymentId = button.getAttribute('data-payment-id');
        if (!paymentId) return;

        var originalTitle = button.getAttribute('title') || '';

        try {
            await copyToClipboard(paymentId);
            button.setAttribute('title', '{{ __('Copied') }}');
            showCopyToast('{{ __('Copied payment ID') }}', false);
        } catch (error) {
            button.setAttribute('title', '{{ __('Copy failed') }}');
            showCopyToast('{{ __('Copy failed') }}', true);
        }

        setTimeout(function () {
            button.setAttribute('title', originalTitle);
        }, 1200);
    });
</script>
@endpush
