@extends('layouts.admin')
@section('title', __('E-mail Templates'))

{{-- Doc 15 section 15.20: the platform's e-mails, written per language. Expects $groups and $languages. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
@endphp

@section('content')
    <div class="mb-4">
        <h1 class="page-title">{{ __('E-mail Templates') }}</h1>
        <p class="page-subtitle">{{ __('Each e-mail is sent in the words built into izSuite until an active template is saved for it, in the language of the e-mail - else in English. A draft is kept, previewed and tested, not sent.') }}</p>
    </div>

    @foreach($groups as $group => $emails)
        <div class="card overflow-hidden mb-6">
            <div class="p-6 pb-3">
                <h2 class="text-lg font-semibold text-[var(--text-primary)]">{{ __($group) }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('E-mail') }}</th>
                            @foreach($languages as $language)
                                <th>{{ $language->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($emails as $email)
                            <tr>
                                <td class="min-w-[220px]">
                                    <span class="font-semibold text-[var(--text-primary)]">{{ __($email['name']) }}</span>
                                    <code class="block text-xs text-[var(--text-muted)]">{{ $email['key'] }}</code>
                                    @if($email['mandatory'])
                                        <span class="block text-xs text-[var(--text-muted)]">{{ __('Always sent: nobody in a business can turn it off.') }}</span>
                                    @endif
                                </td>
                                @foreach($languages as $language)
                                    @php($template = $email['templates'][$language->code] ?? null)
                                    <td class="whitespace-nowrap">
                                        @if($template)
                                            <span class="badge {{ $template->isActive() ? 'badge-success' : 'badge-warning' }}">{{ $template->isActive() ? __('Active') : __('Draft') }} · v{{ $template->version }}</span>
                                        @else
                                            <span class="text-xs text-[var(--text-muted)]">{{ __('Built-in') }}</span>
                                        @endif
                                        @if($may('admin.email-templates.edit'))
                                            <a href="{{ route('admin.email-templates.edit', [$email['key'], $language->code]) }}" class="btn btn-xs btn-secondary ms-1" title="{{ __('Write') }}"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i></a>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
