@extends('layouts.admin')
@section('title', __('E-mail Template'))

{{-- One platform e-mail in one language (Doc 15 section 15.20). Expects $key, $name, $language, $template, $variables, $button and $versions. --}}
@php
    $role = auth()->user()->role;
    $may  = fn (string $route) => (bool) $role?->hasPermission($route);
    $hint = 'text-xs mt-1 text-[var(--text-muted)]';
@endphp

@section('content')
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
            <a href="{{ route('admin.email-templates.index') }}" class="text-[var(--primary)]">{{ __('E-mail Templates') }}</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span>{{ __($name) }}</span>
        </div>
        <h1 class="page-title">{{ __($name) }} · {{ $language->name }}</h1>
        <p class="page-subtitle">
            <code>{{ $key }}</code> ·
            {{ $template ? ($template->isActive() ? __('Active, version :v: sent in these words.', ['v' => $template->version]) : __('Draft, version :v: the built-in words are sent.', ['v' => $template->version])) : __('Not written yet: the built-in words are sent.') }}
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <form action="{{ route('admin.email-templates.update', [$key, $language->code]) }}" method="POST" class="card lg:col-span-2">
            @csrf
            @method('PUT')
            <div class="mb-4">
                <label class="form-label" for="subject">{{ __('Subject') }}</label>
                <input type="text" id="subject" name="subject" class="form-input" maxlength="200" required value="{{ old('subject', $template->subject ?? '') }}">
            </div>
            <div class="mb-4">
                <label class="form-label" for="body">{{ __('Message') }}</label>
                <textarea id="body" name="body" rows="10" class="form-input" maxlength="10000" required>{{ old('body', $template->body ?? '') }}</textarea>
                <p class="{{ $hint }}">{{ __('A blank line starts a new paragraph. The subject is the e-mail\'s title too.') }}</p>
            </div>
            @if($button)
                <div class="mb-4">
                    <label class="form-label" for="button">{{ __('Button words') }}</label>
                    <input type="text" id="button" name="button" class="form-input" maxlength="60" value="{{ old('button', $template->button ?? '') }}" placeholder="{{ $button }}">
                    <p class="{{ $hint }}">{{ __('The button opens {link}. Empty: ":words".', ['words' => $button]) }}</p>
                </div>
            @endif
            <div class="mb-6 max-w-xs">
                <label class="form-label" for="status">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="draft" @selected(old('status', $template->status ?? 'draft') === 'draft')>{{ __('Draft: kept, not sent') }}</option>
                    <option value="active" @selected(old('status', $template->status ?? 'draft') === 'active')>{{ __('Active: sent in these words') }}</option>
                </select>
            </div>
            @if($may('admin.email-templates.edit'))
                <button type="submit" class="btn btn-primary"><i data-lucide="save" class="w-4 h-4"></i>{{ __('Save as a New Version') }}</button>
            @endif
        </form>

        <div class="space-y-6">
            <div class="card">
                <h3 class="section-title mb-2">{{ __('Variables') }}</h3>
                <p class="text-xs mb-3 text-[var(--text-muted)]">{{ __('Write one in braces; each e-mail fills in its own. The samples are what a preview and a test show.') }}</p>
                <dl class="space-y-2 text-sm">
                    @foreach($variables as $variable => $sample)
                        <div>
                            <dt><code>{{ '{'.$variable.'}' }}</code></dt>
                            <dd class="text-xs text-[var(--text-muted)] break-words">{{ $sample }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            @if($template)
                <div class="card">
                    <h3 class="section-title mb-3">{{ __('Try It') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @if($may('admin.email-templates.preview'))
                            <a href="{{ route('admin.email-templates.preview', [$key, $language->code]) }}" target="_blank" rel="noopener" class="btn btn-secondary"><i data-lucide="eye" class="w-4 h-4"></i>{{ __('Preview') }}</a>
                        @endif
                        @if($may('admin.email-templates.test'))
                            <form action="{{ route('admin.email-templates.test', [$key, $language->code]) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-secondary"><i data-lucide="send" class="w-4 h-4"></i>{{ __('Send Me a Test') }}</button>
                            </form>
                        @endif
                    </div>
                    <p class="{{ $hint }}">{{ __('Both use the saved version, with the samples.') }}</p>
                </div>
            @endif
        </div>
    </div>

    @include('admin.email-templates._versions')
@endsection
