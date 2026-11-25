{{--
    The profile image picker (users.avatar, saved by ProfileImages): pass the
    account being edited as $user, or null on a form that creates one. Its form
    needs enctype="multipart/form-data".
--}}
@php
    $avatarUrl = ! empty($user?->avatar) ? asset('storage/'.$user->avatar) : null;
@endphp

<x-file-picker
    name="avatar"
    accept="image/jpeg,image/png,image/webp"
    :label="__('Profile Image')"
    :hint="__('JPG, PNG or WebP · max 2 MB')"
    :existing-url="$avatarUrl"
    :existing-name="$avatarUrl ? __('Current profile image') : null">
    @error('avatar')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</x-file-picker>
