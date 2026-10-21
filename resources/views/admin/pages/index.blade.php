@extends('layouts.admin')
@section('title', __('Pages'))

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New Page') }}
        </a>
    </div>

    {{-- Success flash --}}
    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Slug') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Footer') }}</th>
                        <th>{{ __('Updated') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $pages->firstItem() + $loop->index }}</td>
                            <td class="font-semibold text-[var(--text-primary)]">{{ $page->title }}</td>
                            <td>
                                <code class="text-xs px-2 py-0.5 rounded bg-[var(--card-bg)]">/page/{{ $page->slug }}</code>
                            </td>
                            <td>
                                @if($page->status === 'published')
                                    <span class="badge badge-success">{{ __('Published') }}</span>
                                @else
                                    <span class="badge badge-warning">{{ __('Draft') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($page->show_in_footer)
                                    <i data-lucide="check-circle" class="w-4 h-4 text-[var(--primary)]"></i>
                                @else
                                    <i data-lucide="minus" class="w-4 h-4 text-[var(--text-muted)]"></i>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $page->updated_at->diffForHumans() }}</td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="btn btn-xs btn-secondary" title="{{ __('Preview') }}">
                                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="{{ route('admin.pages.edit', $page->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-page-{{ $page->id }}')">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>

                                    <x-confirm-modal
                                        id="delete-page-{{ $page->id }}"
                                        :title="__('Delete Page?')"
                                        :message="__('Are you sure you want to delete this page? This action cannot be undone.')"
                                        :action="route('admin.pages.destroy', $page->id)"
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
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="file-text" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No pages yet. Create your first page.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pages->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $pages->links() }}
            </div>
        @endif
    </div>
@endsection
