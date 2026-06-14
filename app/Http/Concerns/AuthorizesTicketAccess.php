<?php

namespace App\Http\Concerns;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Shared ticket access authorization logic used by TicketController
 * and AttachmentController to avoid duplication (DRY principle).
 */
trait AuthorizesTicketAccess
{
    /**
     * Ensure the given user may access the given ticket.
     * Employees may only access their own tickets.
     *
     * @throws AuthorizationException
     */
    protected function authorizeTicketAccess(Ticket $ticket, User $user): void
    {
        if ($user->isEmployee() && $ticket->user_id !== $user->id) {
            throw new AuthorizationException('You can only access your own tickets.');
        }
    }
}
