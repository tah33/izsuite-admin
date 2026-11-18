{{-- A payment's status as a badge (Doc 15 Phase C2a), with what of it izSuite refunded and a refund still waiting. Expects $payment, $refunded and $refundPending. --}}
@php
    [$class, $label] = match (true) {
        $payment->notApplied()                     => ['badge-danger', __('Not applied')],
        $payment->succeeded()                      => ['badge-success', __('Paid')],
        $payment->status === 'failed'              => ['badge-danger', __('Declined')],
        $payment->status === 'action_required'     => ['badge-warning', __('Cardholder to confirm')],
        default                                    => ['badge-warning', __('Processing')],
    };
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
@if((float) ($refunded ?? 0) > 0)
    <span class="block text-xs text-[var(--text-muted)] mt-1">{{ __('Refunded :amount', ['amount' => $payment->currency.' '.number_format((float) $refunded, 2)]) }}</span>
@endif
@if($refundPending ?? false)
    <span class="block text-xs text-[var(--warning)] mt-1">{{ __('A refund waits for the provider') }}</span>
@endif
