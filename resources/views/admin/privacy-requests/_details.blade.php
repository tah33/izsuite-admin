{{-- What a privacy request asked, how its asker was checked, and its answer (Doc 15 section 15.24). Expects $privacy, $Privacy and $may. --}}
<div class="card">
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
        @foreach([
            __('Asked by')    => $privacy->requester_name.' · '.$privacy->requester_email,
            __('Came')        => __($Privacy::CHANNELS[$privacy->received_via] ?? $privacy->received_via).' · '.$privacy->received_on->format('M d, Y'),
            __('The account') => $privacy->user ? $privacy->user->name.' · '.$privacy->user->email : __('None named'),
            __('The business') => $privacy->workspace ? $privacy->workspace->name.' · '.$privacy->workspace->businessId() : __('None named'),
            __('Logged by')   => ($privacy->creator?->name ?? '-').' · '.$privacy->created_at->format('M d, Y H:i'),
        ] as $label => $value)
            <div>
                <dt class="text-[var(--text-muted)]">{{ $label }}</dt>
                <dd class="font-medium text-[var(--text-primary)] break-words">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    <p class="mt-4 text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $privacy->details }}</p>
    <div class="flex flex-wrap gap-2 mt-4">
        @if($privacy->user && $may('admin.clients.show'))
            <a href="{{ route('admin.clients.show', $privacy->user->id) }}" class="btn btn-xs btn-secondary"><i data-lucide="user" class="w-3.5 h-3.5"></i>{{ __('The Account') }}</a>
        @endif
        @if($privacy->workspace && $may('admin.businesses.show'))
            <a href="{{ route('admin.businesses.show', $privacy->workspace->id) }}" class="btn btn-xs btn-secondary"><i data-lucide="building-2" class="w-3.5 h-3.5"></i>{{ __('The Business') }}</a>
        @endif
    </div>
</div>

<div class="card">
    <h2 class="section-title mb-2">{{ __('Who Is Asking') }}</h2>
    @if($privacy->identity_check)
        <p class="text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $privacy->identity_check }}</p>
        <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('Checked by :name on :date.', ['name' => $privacy->verifier?->name ?? '-', 'date' => $privacy->verified_at?->format('M d, Y H:i')]) }}</p>
    @else
        <p class="text-sm text-[var(--text-muted)]">{{ __('Not checked yet. Nothing is done until it is.') }}</p>
    @endif
</div>

@if($privacy->outcome)
    <div class="card">
        <h2 class="section-title mb-2">{{ $privacy->status === $Privacy::REJECTED ? __('Why It Was Rejected') : __('What Was Done') }}</h2>
        <p class="text-sm whitespace-pre-line text-[var(--text-secondary)]">{{ $privacy->outcome }}</p>
        <p class="text-xs mt-2 text-[var(--text-muted)]">{{ __('By :name on :date.', ['name' => $privacy->completer?->name ?? __('izSuite'), 'date' => $privacy->completed_at?->format('M d, Y H:i')]) }}</p>
        @if($privacy->export_ready_at)
            <p class="text-xs mt-2 text-[var(--text-muted)]">
                {{ __(':size, made :made; its link :expires. Downloaded :count times.', [
                    'size'    => \App\Support\Bytes::format($privacy->export_size ?? 0),
                    'made'    => $privacy->export_ready_at->format('M d, Y H:i'),
                    'expires' => $privacy->exportAvailable() ? __('works until :date', ['date' => $privacy->export_expires_at->format('M d, Y H:i')]) : __('expired on :date, and the file is deleted', ['date' => $privacy->export_expires_at?->format('M d, Y')]),
                    'count'   => $privacy->export_downloads,
                ]) }}
            </p>
        @endif
    </div>
@endif
