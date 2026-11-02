<?php

namespace App\Services\Api\Members;

use App\Mail\WorkspaceInvitation;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * An invitation's secure link (Doc 12 sections 6 and 23): a fresh secret for
 * each sending - only its hash is kept, so the link cannot be read back - and
 * the e-mail that carries it to the frontend's /invitations/{secret}.
 */
class InvitationSender
{
    /**
     * A new secret, and what the member keeps of it: the hash, until when it
     * works, and who sent it when.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function issue(User $inviter): array
    {
        $secret = Str::random(48);

        return [$secret, [
            'invitation_token'      => self::hash($secret),
            'invitation_expires_at' => now()->addDays($this->days()),
            'invited_at'            => now(),
            'invited_by'            => $inviter->id,
        ]];
    }

    /** True once the e-mail is on its way; false - reported - when it could not be sent. */
    public function send(Workspace $workspace, WorkspaceMember $member, User $inviter, string $secret): bool
    {
        try {
            Mail::to($member->email)->send(new WorkspaceInvitation(
                (string) $member->name,
                $workspace->name,
                $inviter->name,
                $member->role->name,
                rtrim((string) config('app.frontend_url'), '/').'/invitations/'.$secret,
                $this->days(),
            ));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    private function days(): int
    {
        return max(1, (int) config('invitations.days', 7));
    }
}
