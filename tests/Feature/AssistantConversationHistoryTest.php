<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Organization;
use App\Models\User;
use function Pest\Faker\fake;

function createTestOrganization(User $user): Organization
{
    $organization = Organization::create([
        'name' => fake()->company(),
        'slug' => fake()->unique()->slug(),
    ]);

    $organization->users()->attach($user, ['role' => 'owner']);

    return $organization;
}

test('assistant history page lists conversations', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    // Create two conversations.
    $conv1 = AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'First conversation',
        'last_message_at' => now()->subHour(),
    ]);

    $conv2 = AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'Second conversation',
        'last_message_at' => now(),
    ]);

    // Add messages.
    $conv1->messages()->create(['role' => 'user', 'content' => 'What is my rent?']);
    $conv2->messages()->create(['role' => 'user', 'content' => 'Show vacant units']);

    $this->actingAs($user)
        ->get(route('tenant.assistant.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/assistant/history')
            ->has('conversations', 2)
            ->where('conversations.0.title', 'Second conversation')
            ->where('conversations.1.title', 'First conversation'));
});

test('assistant history shows preview from first user message', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'Test',
        'last_message_at' => now(),
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => str_repeat('A', 200)]);

    $this->actingAs($user)
        ->get(route('tenant.assistant.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.0.preview', str_repeat('A', 120)));
});

test('conversation detail page shows messages', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'My chat',
        'last_message_at' => now(),
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'Hello']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Hi there!']);

    $this->actingAs($user)
        ->get(route('tenant.assistant.conversation', $conversation->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/assistant/conversation')
            ->where('conversation.title', 'My chat')
            ->has('messages', 2)
            ->where('messages.0.role', 'user')
            ->where('messages.0.content', 'Hello')
            ->where('messages.1.role', 'assistant')
            ->where('messages.1.content', 'Hi there!'));
});

test('conversation detail returns 404 for non-existent conversation', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $this->actingAs($user)
        ->get(route('tenant.assistant.conversation', 99999))
        ->assertRedirect();
});

test('delete conversation removes it and its messages', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'To delete',
        'last_message_at' => now(),
    ]);

    $conversation->messages()->create(['role' => 'user', 'content' => 'Test']);

    $this->actingAs($user)
        ->deleteJson("/api/org/assistant/conversations/{$conversation->id}")
        ->assertOk();

    $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
    $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $conversation->id]);
});

test('cannot delete another users conversation', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $otherUser = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    $conversation = AiConversation::create([
        'user_id' => $otherUser->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'Not mine',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/org/assistant/conversations/{$conversation->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
});

test('ask endpoint accepts title for new conversation', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    // Mock the assistant to avoid real API calls.
    $this->mock(\App\Support\Ai\AssistantService::class, function ($mock) {
        $mock->shouldReceive('ask')->once()->andReturn([
            'answer' => 'Test answer',
            'artifacts' => [],
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('tenant.assistant.ask'), [
            'question' => 'What is my rent?',
            'new_conversation' => true,
            'title' => 'Rent inquiry',
        ])
        ->assertOk()
        ->assertJson(['answer' => 'Test answer']);

    $conversation = AiConversation::where('user_id', $user->id)
        ->where('scope', 'org')
        ->latest()
        ->first();

    expect($conversation->title)->toBe('Rent inquiry');
});

test('history is scoped to user - cannot see other users conversations', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $otherUser = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);

    AiConversation::create([
        'user_id' => $otherUser->id,
        'scope' => 'org',
        'organization_id' => $organization->id,
        'title' => 'Other user chat',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('tenant.assistant.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations', []));
});

test('history is scoped to organization - cannot see other org conversations', function () {
    $user = User::factory()->create(['onboarded_at' => now()]);
    $organization = createTestOrganization($user);
    $otherOrg = createTestOrganization($user);

    AiConversation::create([
        'user_id' => $user->id,
        'scope' => 'org',
        'organization_id' => $otherOrg->id,
        'title' => 'Other org chat',
        'last_message_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('tenant.assistant.history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations', []));
});
