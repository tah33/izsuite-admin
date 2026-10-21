@extends('layouts.admin')
@section('title', __('Contact Messages'))

@section('content')
    <div class="mb-4 flex justify-end gap-2">
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--card-bg)] border border-[var(--card-border)] text-sm">
            <span class="w-2 h-2 rounded-full bg-[var(--warning)]"></span>
            <span>{{ $messages->where('status', 'new')->count() }} {{ __('New') }}</span>
        </div>
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[var(--card-bg)] border border-[var(--card-border)] text-sm">
            <span class="w-2 h-2 rounded-full bg-[var(--primary)]"></span>
            <span>{{ $messages->where('status', 'replied')->count() }} {{ __('Replied') }}</span>
        </div>
    </div>

    <div class="card overflow-hidden shadow-sm border-[var(--card-border)]">
        <div class="overflow-x-auto">
            <table class="data-table w-full">
                <thead>
                    <tr class="bg-[var(--bg-secondary)] border-b border-[var(--card-border)]">
                        <th class="px-6 py-4 text-start text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] w-10 text-center">#</th>
                        <th class="px-6 py-4 text-start text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]">{{ __('Sender') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]">{{ __('Subject & Message') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]">{{ __('Status') }}</th>
                        <th class="px-6 py-4 text-start text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]">{{ __('Submitted') }}</th>
                        <th class="px-6 py-4 text-end text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)]">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--card-border)]">
                    @forelse($messages as $message)
                        <tr class="hover:bg-[var(--bg-secondary)] transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-medium text-[var(--text-muted)] text-center">
                                {{ (($messages->currentPage() - 1) * $messages->perPage()) + $loop->iteration }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center font-bold text-sm">
                                        {{ strtoupper(substr($message->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[var(--text-primary)]">{{ $message->name }}</div>
                                        <div class="text-xs text-[var(--text-muted)]">{{ $message->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-[var(--text-primary)] max-w-xs truncate">{{ $message->subject }}</div>
                                <div class="text-xs text-[var(--text-muted)] max-w-xs truncate mt-0.5">{{ Str::limit($message->message, 60) }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($message->status === 'replied')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-[var(--primary-light)] text-[var(--primary)] border border-[var(--primary)]/10">
                                        <i data-lucide="check-circle-2" class="w-3 h-3 me-1"></i>
                                        {{ __('Replied') }}
                                    </span>
                                @elseif($message->status === 'closed')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-[var(--bg-secondary)] text-[var(--text-muted)] border border-[var(--card-border)]">
                                        {{ __('Closed') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-[var(--warning-light)] text-[var(--warning)] border border-[var(--warning)]/10">
                                        <i data-lucide="mail-plus" class="w-3 h-3 me-1"></i>
                                        {{ __('New') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-[var(--text-muted)]">
                                <div class="flex items-center gap-1.5" title="{{ $message->created_at?->format('Y-m-d H:i') }}">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                    {{ $message->created_at?->diffForHumans() }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-end">
                                <a href="{{ route('admin.contact-messages.show', $message->id) }}" 
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--bg-secondary)] text-[var(--text-muted)] hover:bg-[var(--primary)] hover:text-white transition-all">
                                    <i data-lucide="chevron-right" class="w-4 h-4 text-inherit"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-[var(--text-muted)]">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 rounded-full bg-[var(--bg-secondary)] flex items-center justify-center mb-4">
                                        <i data-lucide="mail-x" class="w-8 h-8 opacity-20"></i>
                                    </div>
                                    <p class="text-sm font-medium">{{ __('No contact messages found.') }}</p>
                                    <p class="text-xs mt-1 opacity-70">{{ __('Messages submitted through the contact form will appear here.') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($messages->hasPages())
            <div class="px-6 py-4 border-t border-[var(--card-border)] bg-[var(--bg-secondary)]">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
@endsection
