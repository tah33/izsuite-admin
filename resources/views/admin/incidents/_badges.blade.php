{{-- An incident's severity and status as badges (Doc 15 section 15.23). Expects $incident. --}}
<span class="badge {{ ['critical' => 'badge-danger', 'major' => 'badge-warning', 'minor' => 'badge-info'][$incident->severity] ?? 'badge-inactive' }}">{{ __(\App\Models\Admin\Incident::SEVERITIES[$incident->severity] ?? $incident->severity) }}</span>
<span class="badge {{ $incident->isResolved() ? 'badge-success' : 'badge-warning' }}">{{ __(\App\Models\Admin\Incident::STATUSES[$incident->status] ?? $incident->status) }}</span>
