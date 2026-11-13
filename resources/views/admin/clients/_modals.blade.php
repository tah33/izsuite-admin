{{-- The dialogs behind _actions' buttons, each only where its button shows. --}}
@php($locked = $client->status === \App\Models\User\User::LOCKED)

@if($may('admin.clients.recovery'))
    @include('admin.partials.reason-modal', [
        'id'     => 'client-recovery-modal',
        'title'  => __('Send a Reset Link'),
        'text'   => __('E-mails :email a link to choose a new password. It works once, for :minutes minutes, and a new password signs them out everywhere. Nobody else sees the link.', ['email' => $client->email, 'minutes' => \App\Services\Api\Auth\PasswordResetService::LINK_TTL_MINUTES]),
        'route'  => route('admin.clients.recovery', $client->id),
        'button' => __('Send Link'),
    ])
@endif

@if($may('admin.clients.sign-out'))
    @include('admin.partials.reason-modal', [
        'id'     => 'client-sign-out-modal',
        'title'  => __('Sign Out Everywhere'),
        'text'   => trans_choice('Ends the account\'s one open session. Its password stays as it is.|Ends the account\'s :count open sessions, on every device. Its password stays as it is.', $client->sessions_count, ['count' => $client->sessions_count]),
        'route'  => route('admin.clients.sign-out', $client->id),
        'button' => __('Sign Out Everywhere'),
    ])
@endif

@if($locked && $may('admin.clients.unlock'))
    @include('admin.partials.reason-modal', [
        'id'     => 'client-unlock-modal',
        'title'  => __('Unlock the Account'),
        'text'   => __('They can sign in again, and are told by e-mail.'),
        'route'  => route('admin.clients.unlock', $client->id),
        'button' => __('Unlock'),
    ])
@elseif(! $locked && $may('admin.clients.lock'))
    @include('admin.partials.reason-modal', [
        'id'     => 'client-lock-modal',
        'title'  => __('Lock the Account'),
        'text'   => __('Nobody can sign in to it until it is unlocked, and its open sessions end now. Their businesses keep working for everyone else in them. They are told by e-mail, without the reason.'),
        'route'  => route('admin.clients.lock', $client->id),
        'button' => __('Lock'),
        'danger' => true,
    ])
@endif

@if($client->two_factor_confirmed_at && $may('admin.clients.two-factor'))
    @include('admin.clients._two-factor-modal')
@endif
