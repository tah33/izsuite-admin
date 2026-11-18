{{-- A user account's status as a badge: Active, Locked by izSuite support (Doc 15 section 15.7), Closed at its holder's request (section 15.24), or Inactive. Expects $status. --}}
<span class="badge {{ [
    'active' => 'badge-success',
    'locked' => 'badge-danger',
    'closed' => 'badge-inactive',
][$status] ?? 'badge-inactive' }}">{{ [
    'active' => __('Active'),
    'locked' => __('Locked'),
    'closed' => __('Closed'),
][$status] ?? __('Inactive') }}</span>
