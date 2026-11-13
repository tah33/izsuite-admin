{{-- A user account's status as a badge: Active, Locked by izSuite support (Doc 15 section 15.7), or Inactive. Expects $status. --}}
<span class="badge {{ [
    'active' => 'badge-success',
    'locked' => 'badge-danger',
][$status] ?? 'badge-inactive' }}">{{ [
    'active' => __('Active'),
    'locked' => __('Locked'),
][$status] ?? __('Inactive') }}</span>
