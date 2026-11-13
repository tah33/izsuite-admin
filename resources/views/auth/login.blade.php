@extends('layouts.guest')
@section('title', 'Sign In')

@section('content')
    <h2 class="text-xl font-bold mb-1 text-[var(--text-primary)]">Welcome back</h2>
    <p class="text-sm mb-6 text-[var(--text-secondary)]">Sign in to access your dashboard</p>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg text-sm bg-[var(--danger-light)]">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            {{-- Same form-input-group shape <x-password-input> renders below,
                 so the two fields carry matching leading icons. --}}
            <div class="form-input-group">
                <i data-lucide="mail"></i>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input form-input-icon-start" placeholder="you@example.com" required autofocus>
            </div>
        </div>

        {{-- No "Remember me": an admin session ends after 30 idle minutes (Doc 15 section 15.2). --}}
        <div class="mb-6">
            <label for="password" class="form-label">Password</label>
            <x-password-input id="password" name="password" placeholder="********" autocomplete="current-password" required />
        </div>

        <button type="submit" class="btn btn-primary w-full">
            Sign In
        </button>
    </form>
@endsection
