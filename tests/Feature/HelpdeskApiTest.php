<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HelpdeskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_user_can_register_login_and_read_profile(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Employee',
            'email' => 'new.employee@example.com',
            'password' => 'secret123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.employee@example.com')
            ->assertJsonPath('data.role_id', Role::EMPLOYEE_ID)
            ->assertJsonPath('data.role.name', Role::EMPLOYEE);

        $createdUser = User::where('email', 'new.employee@example.com')->firstOrFail();

        $this->assertNotSame('secret123', $createdUser->password);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'new.employee@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonStructure(['access_token', 'expires_in', 'user' => ['role']]);

        $this->getJson('/api/auth/me', $this->bearerHeaders($loginResponse->json('access_token')))
            ->assertOk()
            ->assertJsonPath('data.email', 'new.employee@example.com');
    }

    public function test_employee_can_only_access_their_own_tickets(): void
    {
        $employee = User::where('email', 'employee@company.com')->firstOrFail();
        $otherTicket = $this->createTicketFor(User::factory()->create());

        $response = $this->getJson('/api/tickets', $this->authHeaders($employee))
            ->assertOk();

        $ticketIds = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ticketIds->contains($otherTicket->id));

        $this->getJson("/api/tickets/{$otherTicket->id}", $this->authHeaders($employee))
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only access your own tickets.');
    }

    public function test_support_can_update_ticket_and_tags_but_cannot_assign_to_employee(): void
    {
        $support = User::where('email', 'it@company.com')->firstOrFail();
        $employee = User::where('email', 'employee@company.com')->firstOrFail();
        $ticket = Ticket::firstOrFail();
        $tag = Tag::firstOrFail();

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $support->id,
            'tags' => [$tag->id],
        ], $this->authHeaders($support))
            ->assertOk()
            ->assertJsonPath('data.status', Ticket::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.assigned_to', $support->id);

        $this->assertDatabaseHas('ticket_tag', [
            'ticket_id' => $ticket->id,
            'tag_id' => $tag->id,
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'assigned_to' => $employee->id,
        ], $this->authHeaders($support))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_only_admin_can_delete_tickets(): void
    {
        $support = User::where('email', 'it@company.com')->firstOrFail();
        $admin = User::where('email', 'admin@company.com')->firstOrFail();
        $ticket = Ticket::firstOrFail();

        $this->deleteJson("/api/tickets/{$ticket->id}", [], $this->authHeaders($support))
            ->assertForbidden();

        $this->deleteJson("/api/tickets/{$ticket->id}", [], $this->authHeaders($admin))
            ->assertNoContent();

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_attachments_are_private_to_ticket_owner_and_support_staff(): void
    {
        Storage::fake('local');

        $employee = User::where('email', 'employee@company.com')->firstOrFail();
        $support = User::where('email', 'it@company.com')->firstOrFail();
        $otherEmployee = User::factory()->create();
        $ticket = Ticket::where('user_id', $employee->id)->firstOrFail();

        $uploadResponse = $this->post("/api/tickets/{$ticket->id}/attachments", [
            'file' => UploadedFile::fake()->create('error-log.txt', 1, 'text/plain'),
        ], $this->authHeaders($employee))
            ->assertCreated()
            ->assertJsonPath('data.original_name', 'error-log.txt')
            ->assertJsonPath('data.uploaded_by', $employee->id);

        Storage::disk('local')->assertExists($uploadResponse->json('data.file_path'));

        $attachmentId = $uploadResponse->json('data.id');

        $this->get("/api/attachments/{$attachmentId}/download", $this->authHeaders($otherEmployee))
            ->assertForbidden();

        $this->get("/api/attachments/{$attachmentId}/download", $this->authHeaders($support))
            ->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return $this->bearerHeaders(auth('api')->login($user));
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ];
    }

    private function createTicketFor(User $user): Ticket
    {
        return Ticket::create([
            'title' => 'Other employee ticket',
            'description' => 'A ticket created by another employee.',
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_MEDIUM,
            'user_id' => $user->id,
            'category_id' => Category::firstOrFail()->id,
        ]);
    }
}
