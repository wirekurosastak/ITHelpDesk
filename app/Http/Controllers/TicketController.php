<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $query = Ticket::query()
            ->with(['category', 'tags', 'assignee', 'user'])
            ->latest();

        if ($user->isEmployee()) {
            $query->where('user_id', $user->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tagIds = $validated['tags'] ?? [];

        $ticket = Ticket::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'] ?? Ticket::PRIORITY_MEDIUM,
            'status' => Ticket::STATUS_OPEN,
            'user_id' => auth('api')->user()->id,
            'category_id' => $validated['category_id'],
        ]);

        if ($tagIds !== []) {
            $ticket->tags()->sync($tagIds);
        }

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => $ticket->load(['category', 'tags', 'user', 'assignee']),
        ], 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $user = auth('api')->user();

        $this->authorizeTicketAccess($ticket, $user);

        return response()->json([
            'data' => $ticket->load(['category', 'tags', 'attachments.uploader', 'user', 'assignee']),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $user = auth('api')->user();

        $this->authorizeTicketAccess($ticket, $user);
        $this->authorizeTicketUpdate($ticket, $user);
        $this->ensureEditableFieldWasProvided($request, $user);

        $validated = $request->validated();
        $tagIds = $validated['tags'] ?? null;
        unset($validated['tags']);

        if ($user->isSupportStaff()) {
            $this->validateAssignee($validated);
        }

        if ($validated !== []) {
            $ticket->update($validated);
        }

        if ($user->isSupportStaff() && $request->exists('tags')) {
            $ticket->tags()->sync($tagIds ?? []);
        }

        return response()->json([
            'message' => 'Ticket updated successfully.',
            'data' => $ticket->refresh()->load(['category', 'tags', 'user', 'assignee']),
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json(null, 204);
    }

    private function authorizeTicketAccess(Ticket $ticket, User $user): void
    {
        if ($user->isEmployee() && $ticket->user_id !== $user->id) {
            throw new AuthorizationException('You can only access your own tickets.');
        }
    }

    private function authorizeTicketUpdate(Ticket $ticket, User $user): void
    {
        if ($user->isEmployee() && ! $ticket->isOpen()) {
            throw new AuthorizationException('Closed or in-progress tickets can no longer be edited by employees.');
        }
    }

    private function ensureEditableFieldWasProvided(Request $request, User $user): void
    {
        $editableFields = $user->isEmployee()
            ? ['description']
            : ['title', 'description', 'status', 'assigned_to', 'priority', 'category_id', 'tags'];

        foreach ($editableFields as $field) {
            if ($request->exists($field)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'request' => 'At least one editable field must be provided.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateAssignee(array $validated): void
    {
        if (! array_key_exists('assigned_to', $validated) || $validated['assigned_to'] === null) {
            return;
        }

        $assignee = User::find($validated['assigned_to']);

        if (! $assignee?->isSupportStaff()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Tickets can only be assigned to IT Support or Admin users.',
            ]);
        }
    }
}
