<?php

namespace App\Http\Concerns;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesTicketAccess
{
    protected function authorizeTicketAccess(Ticket $ticket, User $user): void
    {
        if ($user->isEmployee() && $ticket->user_id !== $user->id) {
            throw new AuthorizationException('You can only access your own tickets.');
        }
    }
}
