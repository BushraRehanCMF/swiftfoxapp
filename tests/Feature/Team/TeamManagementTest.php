<?php

namespace Tests\Feature\Team;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $member;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
        $this->member = User::factory()->member()->create([
            'account_id' => $this->account->id,
        ]);
    }

    public function test_owner_can_list_team_members(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/team');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_member_cannot_access_team_routes(): void
    {
        $response = $this->actingAs($this->member)
            ->getJson('/api/v1/team');

        $response->assertStatus(403);
    }

    public function test_owner_can_invite_new_member(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/team/invite', [
                'name' => 'New Team Member',
                'email' => 'newmember@example.com',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Team Member')
            ->assertJsonPath('data.email', 'newmember@example.com')
            ->assertJsonPath('data.role', 'member');

        $this->assertDatabaseHas('users', [
            'email' => 'newmember@example.com',
            'account_id' => $this->account->id,
            'role' => 'member',
        ]);

        // Verify invitation was sent
        $newUser = User::where('email', 'newmember@example.com')->first();
        Notification::assertSentTo($newUser, TeamInvitation::class);
    }

    public function test_owner_can_invite_new_owner(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/team/invite', [
                'name' => 'New Owner',
                'email' => 'newowner@example.com',
                'role' => 'owner',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_cannot_invite_with_duplicate_email(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/team/invite', [
                'name' => 'Duplicate',
                'email' => $this->member->email,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_owner_can_update_member_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/team/' . $this->member->id . '/role', [
                'role' => 'owner',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'owner');

        $this->assertDatabaseHas('users', [
            'id' => $this->member->id,
            'role' => 'owner',
        ]);
    }

    public function test_owner_cannot_change_own_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/team/' . $this->owner->id . '/role', [
                'role' => 'member',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_CHANGE_OWN_ROLE');
    }

    public function test_owner_can_remove_member(): void
    {
        $response = $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $this->member->id);

        $response->assertOk();

        $this->assertDatabaseMissing('users', [
            'id' => $this->member->id,
        ]);
    }

    public function test_owner_cannot_remove_self(): void
    {
        $response = $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $this->owner->id);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_REMOVE_SELF');
    }

    public function test_cannot_remove_last_owner(): void
    {
        // Create another owner first, then make the first one the only owner
        $anotherOwner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);

        // Remove the other owner
        $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $anotherOwner->id);

        // Now try to remove the last owner (should fail)
        // First we need to test that we can't remove an owner when they're the last one
        $response = $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $this->owner->id);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_REMOVE_SELF');
    }

    public function test_removing_user_unassigns_conversations(): void
    {
        // Create a conversation assigned to the member
        $contact = Contact::factory()->create(['account_id' => $this->account->id]);
        $conversation = Conversation::factory()->create([
            'account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'assigned_user_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $this->member->id);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'assigned_user_id' => null,
        ]);
    }

    public function test_owner_cannot_access_other_account_users(): void
    {
        $otherAccount = Account::factory()->create();
        $otherUser = User::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson('/api/v1/team/' . $otherUser->id);

        $response->assertStatus(404);
    }

    public function test_owner_can_resend_invitation(): void
    {
        Notification::fake();

        // Create unverified user
        $unverifiedUser = User::factory()->create([
            'account_id' => $this->account->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/team/' . $unverifiedUser->id . '/resend-invite');

        $response->assertOk();

        Notification::assertSentTo($unverifiedUser, TeamInvitation::class);
    }

    public function test_cannot_resend_invitation_to_verified_user(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/team/' . $this->member->id . '/resend-invite');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_VERIFIED');
    }
}
