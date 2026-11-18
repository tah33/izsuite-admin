{{-- A business's status (Doc 15 section 15.6) as a badge. Expects $status. --}}
@php($status = $status ?: \App\Support\BusinessStatus::ACTIVE)
<span class="badge {{ [
    'active'            => 'badge-success',
    'restricted'        => 'badge-warning',
    'under_review'      => 'badge-warning',
    'closure_scheduled' => 'badge-warning',
    'suspended'         => 'badge-danger',
    'closed'            => 'badge-inactive',
][$status] ?? 'badge-inactive' }}">{{ __(\App\Support\BusinessStatus::label($status)) }}</span>
