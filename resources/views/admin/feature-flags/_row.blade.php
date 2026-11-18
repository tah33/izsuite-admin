{{-- One feature flag in the list (Doc 15 section 15.12): its state now, audience, days and responsible admin, with the switch. Expects $flag, $now, $badges and $may. --}}
@php
    $state    = $flag->stateAt($now);
    $audience = array_filter([
        $flag->businesses ? trans_choice(':count business named|:count businesses named', count($flag->businesses)) : null,
        $flag->percentage > 0 ? __(':percent% of businesses', ['percent' => $flag->percentage]).($flag->packages ? ' '.__('on :plans', ['plans' => implode(', ', array_map('ucfirst', $flag->packages))]) : '').($flag->countries ? ' '.__('in :countries', ['countries' => implode(', ', $flag->countries)]) : '') : null,
    ]);
@endphp
<tr>
    <td>
        <span class="font-semibold text-[var(--text-primary)]">{{ $flag->name }}</span>
        <code class="block text-xs text-[var(--text-muted)]">{{ $flag->key }}</code>
    </td>
    <td class="whitespace-nowrap"><span class="badge {{ $badges[$state] }}">{{ __(\App\Models\Admin\FeatureFlag::STATES[$state]) }}</span></td>
    <td class="text-xs text-[var(--text-secondary)]">{{ $audience ? implode(' · ', $audience) : __('Nobody yet') }}</td>
    <td class="text-xs whitespace-nowrap">
        {{ $flag->starts_at ? __('From :day', ['day' => $flag->starts_at->format('M d, Y H:i')]) : __('From when switched on') }}
        <span class="block">{{ $flag->ends_at ? __('Until :day', ['day' => $flag->ends_at->format('M d, Y H:i')]) : __('No end') }}</span>
    </td>
    <td class="text-sm">{{ $flag->responsible?->name ?? '-' }}</td>
    <td class="text-end whitespace-nowrap">
        @if($may('admin.feature-flags.switch'))
            <form action="{{ route('admin.feature-flags.switch', $flag->id) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="on" value="{{ $flag->status === 'on' ? 0 : 1 }}">
                <button type="submit" class="btn btn-xs {{ $flag->status === 'on' ? 'bg-[var(--danger-light)] text-[var(--danger)]' : 'btn-secondary' }}">
                    <i data-lucide="{{ $flag->status === 'on' ? 'power-off' : 'power' }}" class="w-3.5 h-3.5"></i>{{ $flag->status === 'on' ? __('Switch Off') : __('Switch On') }}
                </button>
            </form>
        @endif
        @if($may('admin.feature-flags.edit'))
            <a href="{{ route('admin.feature-flags.edit', $flag->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></a>
        @endif
        @if($flag->status === 'off' && $may('admin.feature-flags.destroy'))
            <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-flag-{{ $flag->id }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
            <x-confirm-modal
                id="delete-flag-{{ $flag->id }}"
                :title="__('Delete This Flag?')"
                :message="__(':key is off. Deleted, the code asking for it finds it off. Nothing made while it was on is removed.', ['key' => $flag->key])"
                :action="route('admin.feature-flags.destroy', $flag->id)"
                method="DELETE"
                :confirm-text="__('Delete')"
                confirm-class="btn-danger"
                icon="trash-2"
            />
        @endif
    </td>
</tr>
