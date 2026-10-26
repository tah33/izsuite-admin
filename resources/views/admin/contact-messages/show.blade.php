@extends('layouts.admin')
@section('title', __('Message from') . ' ' . $contactMessage->name)

@push('head')
    {{-- Summernote CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .note-editor.note-frame { border: 1px solid var(--card-border); border-radius: 0.75rem; overflow: hidden; }
        .note-toolbar { background: var(--bg-secondary); border-bottom: 1px solid var(--card-border); }
        .note-btn { background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-primary); }
        .note-btn:hover { background: var(--bg-secondary); }
    </style>
@endpush

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.contact-messages.index') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--card-bg)] border border-[var(--card-border)] text-[var(--text-muted)] hover:text-[var(--primary)] transition-all">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title text-2xl font-bold text-[var(--text-primary)]">{{ $contactMessage->subject }}</h1>
            <p class="page-subtitle text-[var(--text-muted)] mt-1">{{ __('Message ID') }}: #{{ $contactMessage->id }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            {{-- Message History --}}
            <div class="card p-0 overflow-hidden border-[var(--card-border)] shadow-sm">
                <div class="px-6 py-4 bg-[var(--bg-secondary)] border-b border-[var(--card-border)] flex items-center justify-between">
                    <h3 class="font-semibold text-[var(--text-primary)] uppercase tracking-wider text-xs">{{ __('Conversation') }}</h3>
                    <div class="flex items-center gap-2">
                        @if($contactMessage->status === 'replied')
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[var(--primary-light)] text-[var(--primary)]">{{ __('Replied') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-[var(--warning-light)] text-[var(--warning)]">{{ __('New') }}</span>
                        @endif
                    </div>
                </div>

                <div class="p-6 space-y-8">
                    {{-- Incoming Message --}}
                    <div class="flex gap-4">
                        <div class="shrink-0">
                            <div class="w-10 h-10 rounded-full bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center font-bold">
                                {{ strtoupper(substr($contactMessage->name, 0, 1)) }}
                            </div>
                        </div>
                        <div class="flex-1 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-[var(--text-primary)]">{{ $contactMessage->name }}</span>
                                <span class="text-xs text-[var(--text-muted)]">{{ $contactMessage->created_at?->format('F d, Y h:i A') }}</span>
                            </div>
                            <div class="text-xs text-[var(--text-muted)] mb-2">{{ $contactMessage->email }}</div>
                            <div class="p-4 rounded-xl bg-[var(--bg-secondary)] text-[var(--text-primary)] leading-relaxed text-sm whitespace-pre-wrap">
                                {{ $contactMessage->message }}
                            </div>
                        </div>
                    </div>

                    {{-- Admin Reply --}}
                    @if($contactMessage->admin_reply)
                        <div class="flex gap-4 flex-row-reverse">
                            <div class="shrink-0">
                                <div class="w-10 h-10 rounded-full bg-[var(--primary)] text-white flex items-center justify-center">
                                    <i data-lucide="user-check" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div class="flex-1 space-y-1 text-end">
                                <div class="flex items-center justify-between flex-row-reverse">
                                    <span class="font-bold text-[var(--text-primary)]">{{ $contactMessage->replier?->name ?? __('System Admin') }}</span>
                                    <span class="text-xs text-[var(--text-muted)]">{{ $contactMessage->replied_at?->format('F d, Y h:i A') }}</span>
                                </div>
                                <div class="text-xs text-[var(--primary)] font-medium mb-2 uppercase tracking-tight text-[10px]">{{ __('Staff Response') }}</div>
                                <div class="p-4 rounded-xl bg-[var(--primary-light)] text-[var(--primary-dark)] shadow-sm border border-[var(--primary)]/10 text-start">
                                    <div class="prose prose-sm max-w-none text-inherit">
                                        {!! $contactMessage->admin_reply !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Reply Form --}}
            <div class="card border-[var(--card-border)] shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-[var(--primary-light)] text-[var(--primary)] flex items-center justify-center">
                        <i data-lucide="reply" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-semibold text-[var(--text-primary)]">{{ $contactMessage->admin_reply ? __('Update Response') : __('Write a Response') }}</h3>
                </div>
                
                <form action="{{ route('admin.contact-messages.reply', $contactMessage->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] mb-2">{{ __('Mail Subject') }}</label>
                        <input type="text" name="subject" value="{{ old('subject', $contactMessage->reply_subject ?? ('Re: ' . $contactMessage->subject)) }}" 
                            class="form-input w-full px-4 py-2.5 rounded-xl text-sm border-[var(--card-border)] focus:ring-2 focus:ring-[var(--primary)] focus:border-[var(--primary)] transition-all"
                            placeholder="{{ __('Enter response subject...') }}">
                        @error('subject') <p class="form-error text-xs text-[var(--danger)] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] mb-2">{{ __('Response Content') }}</label>
                        <textarea name="reply" id="summernote-reply"
                            placeholder="{{ __('Type your reply here...') }}">{{ old('reply', $contactMessage->admin_reply ?? '') }}</textarea>
                        @error('reply') <p class="form-error text-xs text-[var(--danger)] mt-1">{{ $message }}</p> @enderror
                    </div>
                    
                    <div class="flex items-center justify-between mt-6">
                        <p class="text-xs text-[var(--text-muted)] italic">
                            {{ __('Your reply will be saved in the system for tracking records.') }}
                        </p>
                        <button type="submit" class="btn btn-primary px-6 flex items-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span>{{ $contactMessage->admin_reply ? __('Update Reply') : __('Send Response') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="space-y-6">
            @include('admin.contact-messages._sidebar')
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Summernote JS --}}
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script>
        $(function() {
            $('#summernote-reply').summernote({
                height: 200,
                placeholder: '{{ __("Type your reply here...") }}',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                    ['font', ['strikethrough', 'superscript', 'subscript']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link', 'hr']],
                    ['view', ['fullscreen', 'codeview', 'help']],
                ],
                callbacks: {
                    onInit: function() {
                        $('.note-editable').addClass('text-sm');
                    }
                }
            });
        });
    </script>
@endpush
