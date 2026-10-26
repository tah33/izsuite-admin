@extends('layouts.admin')
@section('title', __('Payment Methods'))

@section('content')
    {{-- Tabs, with the page action at the end of the same row. items-end keeps
         the tabs' underline on the divider while the taller button sits above it. --}}
    <div class="flex items-end gap-1 mb-4 border-b border-[var(--card-border)]">
        <button type="button" class="pm-tab px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 -mb-[1px] {{ $tab === 'offline' ? 'border-[var(--primary)] text-[var(--primary)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}" data-tab="offline">
            <i data-lucide="banknote" class="w-4 h-4 inline-block me-1"></i> {{ __('Offline Methods') }}
        </button>
        <button type="button" class="pm-tab px-4 py-2 text-sm font-medium rounded-t-lg transition-all border-b-2 -mb-[1px] {{ $tab === 'online' ? 'border-[var(--primary)] text-[var(--primary)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text-primary)]' }}" data-tab="online">
            <i data-lucide="credit-card" class="w-4 h-4 inline-block me-1"></i> {{ __('Online Gateways') }}
        </button>

        <a href="{{ route('admin.payment-methods.create') }}" class="btn btn-primary ms-auto mb-2">
            <i data-lucide="plus" class="w-4 h-4"></i>
            {{ __('New Offline Method') }}
        </a>
    </div>

    {{-- Offline Tab --}}
    <div class="pm-panel {{ $tab !== 'offline' ? 'hidden' : '' }}" data-tab="offline">
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offlineMethods as $method)
                            <tr>
                                <td class="text-sm text-[var(--text-muted)]">{{ $loop->iteration }}</td>
                                <td class="font-semibold text-[var(--text-primary)]">{{ $method->name }}</td>
                                <td class="text-sm text-[var(--text-muted)]">{{ Str::limit($method->description, 50) }}</td>
                                <td>
                                    @if($method->is_active)
                                        <span class="badge badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.payment-methods.edit', $method->id) }}" class="btn btn-xs btn-secondary" title="{{ __('Edit') }}">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                        </a>
                                        <button type="button" class="btn btn-xs bg-[var(--danger-light)] text-[var(--danger)]" title="{{ __('Delete') }}" onclick="openConfirmModal('delete-payment-method-{{ $method->id }}')">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <x-confirm-modal
                                            id="delete-payment-method-{{ $method->id }}"
                                            :title="__('Delete Offline Method?')"
                                            :message="__('Are you sure you want to delete this offline payment method? This action cannot be undone.')"
                                            :action="route('admin.payment-methods.destroy', $method->id)"
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
                                <td colspan="5" class="text-center py-8 text-[var(--text-muted)]">
                                    <i data-lucide="banknote" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                    <p>{{ __('No offline payment methods yet.') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Online Tab --}}
    <div class="pm-panel {{ $tab !== 'online' ? 'hidden' : '' }}" data-tab="online">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($onlineMethods as $gateway)
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="gateway-icon w-10 h-10 rounded-lg flex items-center justify-center text-lg font-bold bg-[var(--primary-alpha)] text-[var(--primary)]">
                                {{ strtoupper(substr($gateway->name, 0, 1)) }}
                            </div>
                            <div>
                                <h3 class="font-semibold text-[var(--text-primary)]">{{ $gateway->name }}</h3>
                                <span class="text-xs text-[var(--text-muted)]">{{ $gateway->slug }}</span>
                            </div>
                        </div>
                        @if($gateway->is_active)
                            <span class="badge badge-success text-xs">{{ __('Active') }}</span>
                        @else
                            <span class="badge badge-secondary text-xs">{{ __('Inactive') }}</span>
                        @endif
                    </div>

                    @if($gateway->is_sandbox && $gateway->is_active)
                        <div class="text-xs px-2 py-1 rounded mb-3 inline-block bg-[var(--warning-light)] text-[var(--warning-dark)]">
                            <i data-lucide="flask-conical" class="w-3 h-3 inline-block me-1"></i> {{ __('Sandbox Mode') }}
                        </div>
                    @endif

                    @php $creds = $gateway->credentials ?? []; @endphp
                    <div class="text-xs mb-3 text-[var(--text-muted)]">
                        @if(count($creds) > 0 && collect($creds)->filter()->count() > 0)
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="key" class="w-3 h-3"></i> {{ __('Keys configured') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[var(--danger)]">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> {{ __('Keys not configured') }}
                            </span>
                        @endif
                    </div>

                    <a href="{{ route('admin.payment-methods.edit', $gateway->id) }}" class="btn btn-sm btn-secondary w-full">
                        <i data-lucide="settings" class="w-3.5 h-3.5"></i> {{ __('Configure') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.pm-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const group = tab.dataset.tab;
            
            // Toggle explicit Tailwind classes for active/inactive state
            document.querySelectorAll('.pm-tab').forEach(t => {
                const isActive = (t === tab);
                t.classList.toggle('border-[var(--primary)]', isActive);
                t.classList.toggle('text-[var(--primary)]', isActive);
                
                t.classList.toggle('border-transparent', !isActive);
                t.classList.toggle('text-[var(--text-muted)]', !isActive);
                t.classList.toggle('hover:text-[var(--text-primary)]', !isActive);
            });
            
            // Toggle sections
            document.querySelectorAll('.pm-panel').forEach(p => p.classList.toggle('hidden', p.dataset.tab !== group));
        });
    });
</script>
@endpush


