{{-- A ticket's conversation: one message, with its attachments --}}
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
