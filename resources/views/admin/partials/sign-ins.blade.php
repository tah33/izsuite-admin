{{-- An admin's latest sign-ins to the admin panel and failed attempts (Doc 15 section 15.2). Expects $signIns and $subtitle. --}}
<div class="card p-6">
    <h2 class="text-lg font-semibold text-[var(--text-primary)] mb-1">{{ __('Recent Sign-Ins') }}</h2>
    <p class="text-sm text-[var(--text-muted)] mb-4">{{ $subtitle }}</p>

    @if($signIns->isEmpty())
        <p class="text-sm text-[var(--text-muted)]">{{ __('None yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('Result') }}</th>
                        <th>{{ __('Browser') }}</th>
                        <th>{{ __('IP Address') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($signIns as $signIn)
                        <tr>
                            <td class="text-sm whitespace-nowrap">{{ $signIn->created_at->format('M d, Y H:i') }}</td>
                            <td><span class="badge {{ $signIn->succeeded ? 'badge-success' : 'badge-inactive' }}">{{ __($signIn->outcome()) }}</span></td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $signIn->browser() }}</td>
                            <td class="text-sm text-[var(--text-muted)] font-mono">{{ $signIn->ip_address ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
