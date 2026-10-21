@extends('layouts.admin')
@section('title', __('Ticket') . ' #' . $ticket->id)

@push('head')
    {{-- Summernote CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
@endpush

@section('content')
    {{-- Header / Breadcrumb --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm mb-2 text-[var(--text-muted)]">
                <a href="{{ route('admin.tickets.index') }}" class="hover:underline">← {{ __('Back to tickets') }}</a>
            </div>
            <h1 class="page-title text-2xl">{{ $ticket->subject }}</h1>
        </div>

        {{-- Status / Priority Controls --}}
        <div class="flex items-center gap-3">
            @php
                $priorityColors = [
                    'high'   => 'bg-red-100 text-red-700',
                    'medium' => 'bg-yellow-100 text-yellow-700',
                    'low'    => 'bg-gray-100 text-gray-700',
                ];
            @endphp
            <span class="px-3 py-1 rounded-full text-xs font-bold {{ $priorityColors[$ticket->priority] }}">
                {{ ucfirst($ticket->priority) }}
            </span>
            <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST">
                @csrf
                @method('PUT')
                {{-- A Select2 fills its container, so the container sets the width --}}
                <div class="w-36">
                    <select name="status" onchange="this.form.submit()" class="form-select select-sm" aria-label="{{ __('Status') }}">
                        <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>{{ __('Open') }}</option>
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>{{ __('Resolved') }}</option>
                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Chat Area --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="space-y-4">
                @foreach($ticket->messages as $message)
                    @php
                        $isStaff = $message->user_id !== $ticket->user_id;
                    @endphp

                    <div class="flex gap-4 {{ $isStaff ? 'flex-row-reverse' : '' }}">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-[{{ $isStaff ? 'var(--primary)' : '#94a3b8' }}]">
                                {{ substr($message->user->name, 0, 1) }}
                            </div>
                        </div>

                        <div class="max-w-[85%]">
                            <div class="flex items-baseline gap-2 mb-1 {{ $isStaff ? 'justify-end' : '' }}">
                                <span class="font-semibold text-sm">{{ $message->user->name }}</span>
                                @if($isStaff)
                                    <span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 font-medium">{{ __('Staff') }}</span>
                                @endif
                                <span class="text-xs text-muted">{{ $message->created_at->format('M d, Y, h:i A') }}</span>
                            </div>

                            <div class="p-4 rounded-lg shadow-sm text-sm leading-relaxed
                                {{ $isStaff
                                    ? 'ticket-msg-staff rounded-tr-none'
                                    : 'ticket-msg-user rounded-tl-none'
                                }}">
                                <div class="ticket-msg-content">{!! $message->message !!}</div>
                            </div>

                            {{-- Attachments (below the bubble) --}}
                            @if(!empty($message->attachment_path) && is_array($message->attachment_path))
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach($message->attachment_path as $path)
                                        @php
                                            $filename = basename($path);
                                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                            $size = null;
                                            $fullPath = storage_path('app/public/' . $path);
                                            if (file_exists($fullPath)) {
                                                $bytes = filesize($fullPath);
                                                $size = $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
                                            }
                                        @endphp
                                        <a href="{{ asset('storage/' . $path) }}" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium
                                                  bg-gray-100 text-gray-600 hover:bg-gray-200
                                                  transition no-underline">
                                            <i data-lucide="{{ $isImage ? 'image' : 'file' }}" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                            <span class="truncate max-w-[150px]">{{ $filename }}</span>
                                            @if($size)<span class="opacity-60">({{ $size }})</span>@endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Reply Form --}}
            <div class="mt-8 card">
                <h3 class="section-title mb-3">{{ __('Reply') }}</h3>
                <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4">
                        <textarea id="summernote-reply" name="message">{{ old('message') }}</textarea>
                        @error('message')
                            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- File Upload --}}
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">{{ __('Attachments') }}</label>
                        <div id="file-drop-zone" class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors border-[var(--border-color)]">
                            <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                            <p class="text-sm text-gray-500">{{ __('Drag & drop files or') }} <span class="text-primary font-medium">{{ __('browse') }}</span></p>
                            <p class="text-xs text-gray-400 mt-1">{{ __('Max 10MB per file') }}</p>
                            <input type="file" name="attachments[]" id="file-input" multiple class="hidden"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                        </div>
                        <div id="file-preview" class="flex flex-wrap gap-2 mt-2"></div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="send" class="w-4 h-4 me-2"></i> {{ __('Send Reply') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar Info --}}
        <div class="space-y-6">
            {{-- User Info --}}
            <div class="card">
                <h3 class="section-title text-sm uppercase tracking-wider mb-4 border-b pb-2">{{ __('Requester info') }}</h3>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center text-xl font-bold text-gray-600">
                        {{ substr($ticket->user->name, 0, 1) }}
                    </div>
                    <div>
                        <div class="font-bold">{{ $ticket->user->name }}</div>
                        <div class="text-sm text-muted">{{ $ticket->user->email }}</div>
                    </div>
                </div>
                <div class="text-sm">
                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="text-muted">{{ __('Registered') }}</span>
                        <span>{{ $ticket->user->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span class="text-muted">{{ __('Total Tickets') }}</span>
                        <span>{{ $ticket->user->tickets()->count() }}</span>
                    </div>
                </div>
            </div>

            {{-- Ticket Info --}}
            <div class="card">
                 <h3 class="section-title text-sm uppercase tracking-wider mb-4 border-b pb-2">{{ __('Ticket Details') }}</h3>
                 <div class="text-sm space-y-3">
                    <div class="flex justify-between">
                         <span class="text-muted">{{ __('Created') }}</span>
                         <span>{{ $ticket->created_at->format('M d, Y H:i') }}</span>
                    </div>
                    <div class="flex justify-between">
                         <span class="text-muted">{{ __('Last Activity') }}</span>
                         <span>{{ $ticket->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                         <span class="text-muted">{{ __('Priority') }}</span>
                         <span class="px-2 py-0.5 rounded text-xs font-medium {{ $priorityColors[$ticket->priority] }}">
                             {{ ucfirst($ticket->priority) }}
                         </span>
                    </div>
                    <div class="flex justify-between">
                         <span class="text-muted">{{ __('Messages') }}</span>
                         <span>{{ $ticket->messages->count() }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                         <span class="text-muted">{{ __('Assigned to') }}</span>
                         <span class="font-medium">{{ $ticket->assignee?->name ?? __('Unassigned') }}</span>
                    </div>
                 </div>

                 {{-- Reassignment --}}
                 <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                     <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST">
                         @csrf
                         @method('PUT')
                         <input type="hidden" name="status" value="{{ $ticket->status }}">
                         <label class="block text-xs font-medium mb-1 text-muted">{{ __('Reassign') }}</label>
                         <select name="assigned_to" onchange="this.form.submit()" class="form-select w-full text-sm">
                             <option value="">{{ __('Unassigned') }}</option>
                             @foreach($staff as $member)
                                 <option value="{{ $member->id }}" {{ $ticket->assigned_to == $member->id ? 'selected' : '' }}>
                                     {{ $member->name }}
                                 </option>
                             @endforeach
                         </select>
                     </form>
                 </div>
            </div>
        </div>
    </div>
@endsection





@push('scripts')
    {{-- Summernote JS --}}
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
    <script>
        $(function() {
            // Initialize Summernote WYSIWYG
            $('#summernote-reply').summernote({
                height: 150,
                placeholder: '{{ __("Type your reply...") }}',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'strikethrough']],
                    ['para', ['ul', 'ol']],
                    ['insert', ['link']],
                    ['view', ['codeview']],
                ],
                callbacks: {
                    onChange: function() {
                        lucide.createIcons();
                    }
                }
            });

            // File drop zone
            var dropZone = document.getElementById('file-drop-zone');
            var fileInput = document.getElementById('file-input');
            var preview = document.getElementById('file-preview');

            dropZone.addEventListener('click', function() { fileInput.click(); });
            dropZone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
            dropZone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                fileInput.files = e.dataTransfer.files;
                showPreview(fileInput.files);
            });
            fileInput.addEventListener('change', function() { showPreview(this.files); });

            function showPreview(files) {
                preview.innerHTML = '';
                Array.from(files).forEach(function(file) {
                    var item = document.createElement('div');
                    item.className = 'flex items-center gap-2 px-3 py-1.5 rounded text-xs font-medium bg-gray-100 text-gray-700';
                    var isImage = file.type.startsWith('image/');
                    item.innerHTML = (isImage ? '<i data-lucide="image" class="w-3.5 h-3.5"></i>' : '<i data-lucide="file" class="w-3.5 h-3.5"></i>')
                        + '<span>' + file.name + '</span>'
                        + '<span class="text-gray-400">(' + (file.size / 1024).toFixed(1) + ' KB)</span>';
                    preview.appendChild(item);
                });
                lucide.createIcons();
            }
        });
    </script>
@endpush
