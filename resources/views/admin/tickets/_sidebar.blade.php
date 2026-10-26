{{-- A ticket's sidebar: who asked, and the ticket's details and assignee --}}
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
                <span>{{ $requesterTickets }}</span>
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
