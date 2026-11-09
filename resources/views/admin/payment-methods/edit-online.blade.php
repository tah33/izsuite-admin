@extends('layouts.admin')
@section('title', __('Configure') . ' ' . $method->name)

{{-- An online gateway's keys and switches (Doc 15 section 15 - Doc 13 Phase D2):
     test and live keys apart, kept encrypted and shown again only masked. --}}
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="page-title">{{ __('Configure') }} {{ $method->name }}</h1>
            <p class="page-subtitle">{{ __('Businesses pay izSuite\'s subscription invoices by card on :name\'s own page, and save the card there.', ['name' => $method->name]) }}</p>
        </div>
        <a href="{{ route('admin.payment-methods.index', ['tab' => 'online']) }}" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> {{ __('Back') }}
        </a>
    </div>

    @if($errors->any())
        <div class="p-3 rounded-xl mb-4 text-sm font-medium bg-[var(--danger-light)] text-[var(--danger)]">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('admin.payment-methods.update', $method->id) }}" method="POST" autocomplete="off">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                @foreach(['test' => __('Test keys'), 'live' => __('Live keys')] as $mode => $title)
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold mb-1 text-[var(--text-primary)]">
                            <i data-lucide="key" class="w-5 h-5 inline-block me-1"></i> {{ $title }}
                        </h2>
                        <p class="text-xs mb-4 text-[var(--text-muted)]">
                            {{ $mode === 'test' ? __('From the test mode of your :name account: no real money moves.', ['name' => $method->name]) : __('From the live mode of your :name account: real payments.', ['name' => $method->name]) }}
                            {{ __('Keys are kept encrypted. Leave one blank to keep the key saved.') }}
                        </p>

                        <div class="space-y-4">
                            @foreach($method->credential_fields as $key => $label)
                                @php $saved = $method->masked($mode, $key); @endphp
                                <div class="form-group">
                                    <label for="cred_{{ $mode }}_{{ $key }}" class="form-label">{{ __($label) }}</label>
                                    <input
                                        type="{{ str_contains($key, 'secret') ? 'password' : 'text' }}"
                                        name="credentials[{{ $mode }}][{{ $key }}]"
                                        id="cred_{{ $mode }}_{{ $key }}"
                                        class="form-input font-mono text-sm"
                                        autocomplete="new-password"
                                        placeholder="{{ $saved ? __('Saved: :key', ['key' => $saved]) : __('Not set') }}"
                                    >
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="space-y-4">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-4 text-[var(--text-primary)]">
                        <i data-lucide="settings" class="w-5 h-5 inline-block me-1"></i> {{ __('Status') }}
                    </h2>

                    <label class="flex items-center gap-3 p-3 mb-4 rounded-lg cursor-pointer bg-[var(--body-bg)]">
                        <input type="checkbox" name="is_active" value="1" class="form-checkbox" @checked(old('is_active', $method->is_active))>
                        <div>
                            <span class="text-sm font-medium block text-[var(--text-primary)]">{{ __('Take card payments') }}</span>
                            <span class="text-xs text-[var(--text-muted)]">{{ __('Businesses see Pay by Card on the invoice to pay.') }}</span>
                        </div>
                    </label>

                    <p class="form-label">{{ __('Mode') }}</p>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="radio" name="is_sandbox" value="1" @checked($method->is_sandbox)> {{ __('Test') }}</label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm"><input type="radio" name="is_sandbox" value="0" @checked(! $method->is_sandbox)> {{ __('Live') }}</label>
                    </div>

                    @if($method->is_sandbox)
                        <div class="mt-4 p-3 rounded-lg text-xs bg-[var(--warning-light)] text-[var(--warning-dark)]">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 inline-block me-1"></i>
                            <strong>{{ __('Test mode.') }}</strong> {{ __('Only test cards work and no real charges are made. Switch to Live before going live.') }}
                        </div>
                    @endif
                </div>

                <div class="card p-6">
                    <h2 class="text-lg font-semibold mb-2 text-[var(--text-primary)]">
                        <i data-lucide="webhook" class="w-5 h-5 inline-block me-1"></i> {{ __('Webhook') }}
                    </h2>
                    <p class="text-xs mb-3 text-[var(--text-muted)]">{{ __('In :name, add this endpoint for the event checkout.session.completed, in each mode, and save its signing secret above.', ['name' => $method->name]) }}</p>
                    <input type="text" class="form-input font-mono text-xs" value="{{ route('api.webhooks.stripe') }}" readonly onclick="this.select()" aria-label="{{ __('Webhook address') }}">
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <i data-lucide="save" class="w-4 h-4"></i> {{ __('Save Configuration') }}
                </button>
            </div>
        </div>
    </form>
@endsection
