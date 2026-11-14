{{-- A template's versions, newest first (Doc 15 section 15.20): each kept, and any earlier one restorable as the newest. Expects $key, $language, $template, $versions and $may. --}}
@if($versions->isNotEmpty())
    <div class="card overflow-hidden mt-6">
        <div class="p-6 pb-3">
            <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __('Version History') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Version') }}</th>
                        <th>{{ __('Subject') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Saved') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($versions as $version)
                        <tr>
                            <td class="text-sm font-semibold whitespace-nowrap">v{{ $version->version }}@if($version->version === $template->version) <span class="badge badge-info ms-1">{{ __('Newest') }}</span>@endif</td>
                            <td class="text-sm min-w-[220px]">{{ $version->subject }}</td>
                            <td><span class="badge {{ $version->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ $version->status === 'active' ? __('Active') : __('Draft') }}</span></td>
                            <td class="text-xs whitespace-nowrap">{{ $version->created_at?->format('M d, Y H:i') }}<span class="block text-[var(--text-muted)]">{{ $version->creator?->name }}</span></td>
                            <td class="text-end">
                                @if($version->version !== $template->version && $may('admin.email-templates.restore'))
                                    <form action="{{ route('admin.email-templates.restore', [$key, $language->code, $version->version]) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-secondary"><i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>{{ __('Restore') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
