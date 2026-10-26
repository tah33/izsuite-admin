@extends('layouts.admin')
@section('title', __('Languages'))

@section('content')
    <div class="mb-4 flex justify-end">
        <a href="{{ route('admin.languages.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New Language') }}
        </a>
    </div>
    @if(session('error'))
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">
            {{ session('error') }}
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Language') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Direction') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($languages as $language)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $languages->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-[var(--text-primary)]">{{ $language->name }}</span>
                                    @if($language->is_default)
                                        <span class="badge badge-warning text-xs">{{ __('Default') }}</span>
                                    @endif
                                </div>
                                @if($language->native_name && $language->native_name !== $language->name)
                                    <p class="text-xs mt-0.5 text-[var(--text-muted)]">{{ $language->native_name }}</p>
                                @endif
                            </td>
                            <td>
                                <code class="text-xs px-2 py-0.5 rounded bg-[var(--card-bg)]">{{ $language->code }}</code>
                            </td>
                            <td>
                                <span class="badge {{ $language->direction === 'rtl' ? 'badge-info' : 'badge-secondary' }} text-xs uppercase">
                                    {{ $language->direction }}
                                </span>
                            </td>
                            <td>
                                @if($language->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.languages.translate', $language->id) }}" class="btn btn-xs btn-primary" title="{{ __('Translate') }}">
                                        <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                                    </a>
                                    <a href="{{ route('admin.languages.edit', $language->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                    @unless($language->is_default)
                                        <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-language-{{ $language->id }}')">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <x-confirm-modal
                                            id="delete-language-{{ $language->id }}"
                                            :title="__('Delete Language?')"
                                            :message="__('Are you sure you want to delete this language? This action cannot be undone.')"
                                            :action="route('admin.languages.destroy', $language->id)"
                                            method="DELETE"
                                            :confirm-text="__('Delete')"
                                            confirm-class="btn-danger"
                                            icon="trash-2"
                                        />
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="globe" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No languages yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($languages->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $languages->links() }}
            </div>
        @endif
    </div>
@endsection
