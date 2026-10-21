@extends('layouts.admin')
@section('title', __('Support Tickets'))

@section('content')
    {{-- One toolbar row: status tabs and search on the left, page actions on the right. --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        {{-- Status Tabs --}}
        <div class="tabs">
            <a href="{{ route('admin.tickets.index') }}"
               class="tab {{ !request('status') ? 'active' : '' }}">
               {{ __('All') }}
            </a>
            <a href="{{ route('admin.tickets.index', ['status' => 'open']) }}"
               class="tab {{ request('status') === 'open' ? 'active' : '' }}">
               {{ __('Open') }}
            </a>
            <a href="{{ route('admin.tickets.index', ['status' => 'in_progress']) }}"
               class="tab {{ request('status') === 'in_progress' ? 'active' : '' }}">
               {{ __('In Progress') }}
            </a>
            <a href="{{ route('admin.tickets.index', ['status' => 'resolved']) }}"
               class="tab {{ request('status') === 'resolved' ? 'active' : '' }}">
               {{ __('Resolved') }}
            </a>
            <a href="{{ route('admin.tickets.index', ['status' => 'closed']) }}"
               class="tab {{ request('status') === 'closed' ? 'active' : '' }}">
               {{ __('Closed') }}
            </a>
        </div>

        {{-- Filters --}}
        <form action="{{ route('admin.tickets.index') }}" method="GET" class="flex flex-1 max-md:basis-full flex-wrap items-center gap-2 min-w-0">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="search-input-wrapper flex-1 min-w-[200px]">
                <i data-lucide="search" class="search-icon"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="{{ __('Search tickets...') }}" class="form-input search-input w-full">
            </div>
            <x-per-page-select />
            <button type="submit" class="btn btn-secondary">
                <i data-lucide="search" class="w-4 h-4"></i>{{ __('Filter') }}
            </button>
            <a href="{{ route('admin.tickets.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-secondary self-stretch" title="{{ __('Reset') }}">
                <i data-lucide="x" class="w-4 h-4"></i>
            </a>
        </form>

        <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i> {{ __('Create Ticket') }}
        </a>
    </div>

    {{-- Tickets List --}}
    <div class="space-y-4">
        @forelse($tickets as $ticket)
            <div class="card hover:shadow-md transition-shadow cursor-pointer relative" 
                 onclick="window.location='{{ route('admin.tickets.show', $ticket->id) }}'">
                
                <div class="flex items-start justify-between gap-4">
                    <div class="flex gap-4">
                        {{-- Icon based on status --}}
                        <div class="mt-1">
                            @if($ticket->status === 'open')
                                <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                                </div>
                            @elseif($ticket->status === 'resolved')
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                </div>
                            @elseif($ticket->status === 'in_progress')
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                            @else
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                                    <i data-lucide="archive" class="w-5 h-5"></i>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h3 class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                                    {{ $ticket->subject }}
                                </h3>
                                
                                {{-- Priority Badge --}}
                                @php
                                    $priorityColors = [
                                        'high' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                        'medium' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        'low' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 rounded textxs font-medium {{ $priorityColors[$ticket->priority] }}">
                                    {{ ucfirst($ticket->priority) }}
                                </span>
                            </div>

                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-2 line-clamp-1">
                                {{ $ticket->latestMessage ? $ticket->latestMessage->message : __('No messages yet') }}
                            </p>

                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('By') }} {{ $ticket->user->name }}
                                </span>
                                <span>&bull;</span>
                                <span>{{ $ticket->messages_count ?? 0 }} {{ __('replies') }}</span>
                                <span>&bull;</span>
                                <span>{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Status Badge --}}
                    @php
                        $statusStyles = [
                            'open' => 'bg-orange-50 text-orange-600 border-orange-200',
                            'in_progress' => 'bg-blue-50 text-blue-600 border-blue-200',
                            'resolved' => 'bg-green-50 text-green-600 border-green-200',
                            'closed' => 'bg-gray-50 text-gray-600 border-gray-200',
                        ];
                        $statusLabels = [
                            'open' => __('Open'),
                            'in_progress' => __('In Progress'),
                            'resolved' => __('Resolved'),
                            'closed' => __('Closed'),
                        ];
                    @endphp
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 rounded-full text-sm font-medium border {{ $statusStyles[$ticket->status] }}">
                            {{ $statusLabels[$ticket->status] }}
                        </span>
                        <i data-lucide="message-square" class="w-4 h-4 text-gray-400"></i>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="message-circle" class="w-8 h-8 text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('No tickets found') }}</h3>
                <p class="text-gray-500 mt-1">{{ __('Try adjusting your filters or search query.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($tickets->hasPages())
        <div class="mt-6">
            {{ $tickets->links() }}
        </div>
    @endif
@endsection



