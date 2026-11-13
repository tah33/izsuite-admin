{{-- izSuite support's sessions in the business (Doc 15 section 15.22): those still open first. Its own admin enters a
     live one in the app, in a new tab; anyone allowed ends it. Expects $supportSessions and $may. --}}
<div class="card p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Support Sessions') }}</h2>
            <p class="text-sm text-[var(--text-muted)]">{{ __('izSuite support in this business, for a case. Each ends by itself.') }}</p>
        </div>
        @if($supportSessions->isNotEmpty() && $may('admin.support-sessions.index'))
            <a href="{{ route('admin.support-sessions.index', ['search' => $business->name]) }}" class="btn btn-secondary btn-sm">{{ __('All Sessions') }}</a>
        @endif
    </div>

    @if($supportSessions->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('None yet.') }}</p>
    @else
        <ul class="divide-y divide-[var(--card-border)]">
            @foreach($supportSessions as $session)
                <li class="py-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-medium text-[var(--text-primary)]">
                            {{ $session->admin_name }} · {{ __($session->accessLabel()) }} · {{ __('case #:id', ['id' => $session->ticket_id]) }}
                        </p>
                        <p class="text-xs text-[var(--text-muted)]">
                            {{ $session->isLive()
                                ? __('Open until :time', ['time' => $session->expires_at->format('M d, H:i')])
                                : __('Ended :time', ['time' => ($session->ended_at ?? $session->expires_at)->format('M d, H:i')]) }}
                            · {{ $session->reason }}
                        </p>
                    </div>
                    @if($session->isLive())
                        <div class="flex items-center gap-2">
                            @if($session->user_id === auth()->id() && $may('admin.support-sessions.open'))
                                <a href="{{ route('admin.support-sessions.open', $session->id) }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                                    <i data-lucide="external-link" class="w-4 h-4"></i>{{ __('Enter in the App') }}
                                </a>
                            @endif
                            @if($may('admin.support-sessions.end'))
                                <form action="{{ route('admin.support-sessions.end', $session->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('End') }}</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
