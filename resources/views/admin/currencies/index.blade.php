@extends('layouts.admin')
@section('title', __('Currencies'))

@section('content')
    <div class="mb-4 flex items-center justify-end gap-2">
        <button type="button" class="btn btn-secondary" onclick="openModal('formatting-modal')">
            <i data-lucide="settings" class="w-4 h-4"></i>
            {{ __('Formatting Settings') }}
        </button>
        <a href="{{ route('admin.currencies.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New Currency') }}
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
                        <th>{{ __('Currency') }}</th>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Symbol') }}</th>
                        <th>{{ __('Exchange Rate') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($currencies as $currency)
                        <tr>
                            <td class="text-sm text-[var(--text-muted)]">{{ $currencies->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-[var(--text-primary)]">{{ $currency->name }}</span>
                                    @if($currency->is_default)
                                        <span class="badge badge-warning text-xs">{{ __('Default') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <code class="text-xs px-2 py-0.5 rounded bg-[var(--card-bg)]">{{ $currency->code }}</code>
                            </td>
                            <td>
                                <span class="text-lg font-bold text-[var(--primary)]">{{ $currency->symbol }}</span>
                            </td>
                            <td>
                                <span class="text-sm">{{ number_format($currency->exchange_rate, 6) }}</span>
                            </td>
                            <td>
                                @if($currency->is_active)
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.currencies.edit', $currency->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                    @unless($currency->is_default)
                                        <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-currency-{{ $currency->id }}')">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <x-confirm-modal
                                            id="delete-currency-{{ $currency->id }}"
                                            :title="__('Delete Currency?')"
                                            :message="__('Are you sure you want to delete this currency? This action cannot be undone.')"
                                            :action="route('admin.currencies.destroy', $currency->id)"
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
                            <td colspan="7" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="coins" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No currencies yet.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($currencies->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $currencies->links() }}
            </div>
        @endif
    </div>

    {{-- Formatting Settings Modal --}}
    <div id="formatting-modal" class="confirm-modal-overlay" onclick="closeConfirmModal('formatting-modal', event)">
        <div class="confirm-modal max-w-[600px]" onclick="event.stopPropagation()">
            <div class="confirm-modal-header mb-4">
                <h3 class="text-lg font-bold text-[var(--text-primary)]">{{ __('Currency Formatting') }}</h3>
            </div>

            <form action="{{ route('admin.currencies.save-formatting') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="symbol_position">{{ __('Symbol Position') }}</label>
                        <select name="symbol_position" id="symbol_position" class="form-select">
                            <option value="left" {{ setting('symbol_position') === 'left' ? 'selected' : '' }}>{{ __('Left') }} ($100)</option>
                            <option value="right" {{ setting('symbol_position') === 'right' ? 'selected' : '' }}>{{ __('Right') }} (100$)</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="decimals">{{ __('Decimals') }}</label>
                        <input type="number" name="decimals" id="decimals" class="form-input" value="{{ setting('decimals', 2) }}" min="0" max="8" required>
                    </div>

                    <div>
                        <label class="form-label" for="decimal_separator">{{ __('Decimal Separator') }}</label>
                        <input type="text" name="decimal_separator" id="decimal_separator" class="form-input" value="{{ setting('decimal_separator', '.') }}" required maxlength="5">
                    </div>

                    <div>
                        <label class="form-label" for="thousand_separator">{{ __('Thousand Separator') }}</label>
                        <input type="text" name="thousand_separator" id="thousand_separator" class="form-input" value="{{ setting('thousand_separator', ',') }}" maxlength="5">
                    </div>
                </div>

                <div class="confirm-modal-actions mt-6">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmModal('formatting-modal')">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Save Settings') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }
</script>
@endpush
