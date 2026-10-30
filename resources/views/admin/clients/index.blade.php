@extends('layouts.admin')
@section('title', __('Users'))

@section('content')
    {{-- One toolbar row: filters on the left. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <form action="{{ route('admin.clients.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, email or workspace...') }}" class="form-input search-input w-full">
            </div>
            <div class="w-44 shrink-0">
                <select name="package" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Plan') }}">
                    <option value="">{{ __('All plans') }}</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->key }}" @selected(request('package') === $package->key)>{{ $package->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
            </button>
            <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Workspaces') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Joined') }}</th>
                        <th>{{ __('Last Login') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="user-avatar w-9 h-9">{{ mb_strtoupper(mb_substr($client->name, 0, 1)) }}</div>
                                    <div>
                                        <a href="{{ route('admin.clients.show', $client->id) }}" class="font-semibold text-[var(--text-primary)] hover:text-[var(--primary)]">{{ $client->name }}</a>
                                        <span class="block text-xs text-[var(--text-muted)]">{{ $client->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @forelse($client->workspaces as $workspace)
                                    <span class="block text-sm">
                                        {{ $workspace->name }}
                                        <span class="text-xs text-[var(--text-muted)]">· {{ $workspace->package?->name }}</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-[var(--text-muted)]">{{ __('None') }}</span>
                                @endforelse
                            </td>
                            <td>
                                @if($client->status === 'active')
                                    <span class="badge badge-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge badge-inactive">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $client->created_at?->format('M d, Y') }}</td>
                            <td class="text-sm text-[var(--text-muted)]">{{ $client->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                            <td class="text-end">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.clients.show', $client->id) }}" class="btn btn-xs btn-secondary">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> {{ __('View') }}
                                    </a>

                                    {{-- Opens the website, signed in as the client, in a new tab. data-no-ajax: the
                                         panel's AJAX forms would swallow the redirect to the website. --}}
                                    @if($client->status === 'active' && $client->email_verified_at)
                                        <form action="{{ route('admin.clients.login-as', $client->id) }}" method="POST" target="_blank" rel="noopener" data-no-ajax>
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-primary">
                                                <i data-lucide="log-in" class="w-3.5 h-3.5"></i> {{ __('Login as') }}
                                            </button>
                                        </form>
                                    @else
                                        <span title="{{ $client->status !== 'active' ? __('This user\'s account is not active.') : __('This user has not verified their email yet.') }}">
                                            <button type="button" class="btn btn-xs btn-secondary opacity-50 pointer-events-none" disabled>
                                                <i data-lucide="log-in" class="w-3.5 h-3.5"></i> {{ __('Login as') }}
                                            </button>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[var(--text-muted)]">
                                <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p>{{ __('No users found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
            <div class="p-4 border-t border-[var(--card-border)]">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
@endsection
