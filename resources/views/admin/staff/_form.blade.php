{{-- Shared Staff form partial — used by create.blade.php and edit.blade.php --}}
@php
    $isEdit = isset($staffUser);
@endphp

{{-- Name --}}
<div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="form-label" for="first_name">{{ __('First Name') }}</label>
        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $isEdit ? $staffUser->first_name : '') }}" class="form-input" placeholder="John" required>
        @error('first_name')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="form-label" for="last_name">{{ __('Last Name') }}</label>
        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $isEdit ? $staffUser->last_name : '') }}" class="form-input" placeholder="Doe">
        @error('last_name')
            <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
        @enderror
    </div>
</div>

{{-- Email --}}
<div class="mb-4">
    <label class="form-label" for="email">{{ __('Email Address') }}</label>
    <input type="email" id="email" name="email" value="{{ old('email', $isEdit ? $staffUser->email : '') }}" class="form-input" placeholder="john@example.com" required>
    @error('email')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

{{-- Role --}}
<div class="mb-4">
    <label class="form-label" for="role_id">{{ __('Role') }}</label>
    <select id="role_id" name="role_id" class="form-input" required>
        @unless($isEdit)
            <option value="">{{ __('Select a role') }}</option>
        @endunless
        @foreach($roles as $role)
            <option value="{{ $role->id }}" {{ old('role_id', $isEdit ? $staffUser->role_id : '') == $role->id ? 'selected' : '' }}>
                {{ $role->name }}
            </option>
        @endforeach
    </select>
    @error('role_id')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

<div class="divider"></div>

{{-- Password --}}
<div class="mb-4">
    <label class="form-label" for="password">{{ $isEdit ? __('New Password') : __('Password') }}</label>
    <input type="password" id="password" name="password" class="form-input"
        placeholder="{{ $isEdit ? __('Leave blank to keep current') : '' }}"
        {{ $isEdit ? '' : 'required' }}>
    @if($isEdit)
        <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('Only fill this if you want to change the password.') }}</p>
    @endif
    <p class="text-xs mt-1 text-[var(--text-muted)]">{{ __('At least 8 characters, with upper and lower case, a number and a symbol.') }}</p>
    @error('password')
        <p class="text-xs mt-1 text-[var(--danger)]">{{ $message }}</p>
    @enderror
</div>

{{-- Confirm Password --}}
<div class="mb-4">
    <label class="form-label" for="password_confirmation">{{ $isEdit ? __('Confirm New Password') : __('Confirm Password') }}</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input"
        placeholder="{{ $isEdit ? __('Re-enter new password') : __('Re-enter password') }}"
        {{ $isEdit ? '' : 'required' }}>
</div>
