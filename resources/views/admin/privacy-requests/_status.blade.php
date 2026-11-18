{{-- A privacy request's status as a badge, and Overdue while it is still open after its due day. Expects $privacy. --}}
@php
    $tones = ['received' => 'badge-warning', 'verified' => 'badge-info', 'in_progress' => 'badge-info', 'completed' => 'badge-success', 'rejected' => 'badge-inactive'];
@endphp
<span class="badge {{ $tones[$privacy->status] ?? 'badge-secondary' }}">{{ __(\App\Models\Admin\PrivacyRequest::STATUSES[$privacy->status] ?? $privacy->status) }}</span>
@if($privacy->isOverdue())
    <span class="badge badge-danger ms-1">{{ __('Overdue') }}</span>
@endif
