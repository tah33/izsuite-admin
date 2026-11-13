{{--
    Confirm Modal Component
    -----------------------
    Reusable confirmation dialog — triggered via JS.

    Usage:
    <x-confirm-modal
        id="delete-user-123"
        title="Delete User?"
        message="This action cannot be undone."
        action="/admin/users/123"
        method="DELETE"
        confirm-text="Delete"
        confirm-class="btn-danger"
    />

    Trigger it with: openConfirmModal('delete-user-123')
--}}

@props([
    'id',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'action' => '#',
    'method' => 'POST',
    'confirmText' => 'Confirm',
    'confirmClass' => 'btn-danger',
    'cancelText' => 'Cancel',
    'icon' => 'alert-triangle',
])

<div id="{{ $id }}" class="confirm-modal-overlay" onclick="closeConfirmModal('{{ $id }}', event)">
    <div class="confirm-modal" onclick="event.stopPropagation()">
        {{-- Icon --}}
        <div class="confirm-modal-icon">
            <i data-lucide="{{ $icon }}" class="w-6 h-6"></i>
        </div>

        {{-- Content --}}
        <h3 class="confirm-modal-title">{{ $title }}</h3>
        <p class="confirm-modal-message">{{ $message }}</p>

        {{-- Fields the action sends, when it has any (a critical action's password and reason), then its buttons. --}}
        <form action="{{ $action }}" method="POST">
            @csrf
            @method($method)

            @if(trim($slot) !== '')
                <div class="mb-4">{{ $slot }}</div>
            @endif

            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeConfirmModal('{{ $id }}')">
                    {{ $cancelText }}
                </button>
                <button type="submit" class="btn {{ $confirmClass }}">
                    {{ $confirmText }}
                </button>
            </div>
        </form>
    </div>
</div>
