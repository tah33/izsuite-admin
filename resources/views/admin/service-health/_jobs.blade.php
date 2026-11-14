{{-- The latest failed background jobs (Doc 15 section 15.23): run again where safe, or forgotten. Expects $jobs and $may. --}}
<div class="card overflow-hidden mb-6">
    <div class="mb-4">
        <h3 class="section-title">{{ __('Failed Jobs') }}</h3>
        <p class="text-sm text-[var(--text-muted)]">{{ __('A job runs again here only when it checks before it acts, so it never sends, adds or charges twice. Forgetting one takes it off the list without running it.') }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Job') }}</th>
                    <th>{{ __('Failed') }}</th>
                    <th>{{ __('Why') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                    <tr>
                        <td class="whitespace-nowrap">
                            <span class="font-semibold text-[var(--text-primary)]">{{ $job['name'] }}</span>
                            <span class="block text-xs text-[var(--text-muted)]">{{ $job['queue'] }} · {{ $job['connection'] }}</span>
                        </td>
                        <td class="text-sm whitespace-nowrap">{{ $job['failed_at']->format('M d, Y H:i') }}</td>
                        <td class="text-xs text-[var(--text-secondary)] max-w-[420px]">
                            {{ $job['error'] }}
                            @unless($job['safe'])
                                <span class="block mt-1 text-[var(--text-muted)]">{{ __('Not run again here: :why', ['why' => $job['why']]) }}</span>
                            @endunless
                        </td>
                        <td class="text-end whitespace-nowrap">
                            @if($job['safe'] && $may('admin.service-health.retry'))
                                <form action="{{ route('admin.service-health.retry', $job['uuid']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-secondary" title="{{ __('Run again') }}"><i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>{{ __('Run Again') }}</button>
                                </form>
                            @endif
                            @if($may('admin.service-health.forget'))
                                <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Forget') }}" onclick="openConfirmModal('forget-job-{{ $job['uuid'] }}')"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                <x-confirm-modal
                                    id="forget-job-{{ $job['uuid'] }}"
                                    :title="__('Forget This Job?')"
                                    :message="__(':job is taken off the list and not run again.', ['job' => $job['name']])"
                                    :action="route('admin.service-health.forget', $job['uuid'])"
                                    method="DELETE"
                                    :confirm-text="__('Forget')"
                                    confirm-class="btn-danger"
                                    icon="trash-2"
                                />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-6 text-[var(--text-muted)]">{{ __('No failed jobs.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
