@extends('layouts.admin')
@section('title', __('FAQs'))

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New FAQ') }}
        </a>
    </div>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Question') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($faqs as $faq)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $faqs->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="font-semibold text-[var(--text-primary)]">{{ $faq->question }}</div>
                                <div class="text-sm text-[var(--text-muted)] line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($faq->answer), 90) }}</div>
                            </td>
                            <td>
                                @if($faq->status === 'published')
                                    <span class="badge badge-success">{{ __('Published') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Draft') }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $faq->sort_order }}</td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $faq->updated_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.faqs.edit', $faq->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-faq-{{ $faq->id }}')">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <x-confirm-modal
                                        id="delete-faq-{{ $faq->id }}"
                                        :title="__('Delete FAQ?')"
                                        :message="__('Are you sure you want to delete this FAQ? This action cannot be undone.')"
                                        :action="route('admin.faqs.destroy', $faq->id)"
                                        method="DELETE"
                                        :confirm-text="__('Delete')"
                                        confirm-class="btn-danger"
                                        icon="trash-2"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="circle-help" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No FAQs yet. Create your first FAQ.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($faqs->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $faqs->links() }}
            </div>
        @endif
    </div>
@endsection
