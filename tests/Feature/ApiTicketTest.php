<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function apiAgent(array $attrs = []): array
{
    $user = TestUser::create(array_merge([
        'name' => 'API Agent',
        'email' => 'api-agent-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ], $attrs));

    $result = ApiToken::createToken($user, 'Test Token', ['agent']);

    return [
        'user' => $user,
        'token' => $result['plainTextToken'],
        'headers' => ['Authorization' => 'Bearer '.$result['plainTextToken']],
    ];
}

function createTicket(TestUser $requester, array $overrides = []): Ticket
{
    return Ticket::create(array_merge([
        'reference' => 'ESC-'.str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT),
        'requester_type' => $requester->getMorphClass(),
        'requester_id' => $requester->getKey(),
        'subject' => 'Test Ticket',
        'description' => 'Test description.',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Medium,
    ], $overrides));
}

it('lists tickets', function () {
    $agent = apiAgent();
    createTicket($agent['user']);
    createTicket($agent['user'], ['subject' => 'Second Ticket']);

    $this->getJson('/support/api/v1/tickets', $agent['headers'])
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
});

it('shows ticket detail', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00100']);

    $this->getJson('/support/api/v1/tickets/ESC-00100', $agent['headers'])
        ->assertOk()
        ->assertJsonPath('data.reference', 'ESC-00100')
        ->assertJsonPath('data.subject', 'Test Ticket');
});

it('creates a ticket', function () {
    $agent = apiAgent();

    $this->postJson('/support/api/v1/tickets', [
        'subject' => 'New API Ticket',
        'description' => 'Created via API',
        'priority' => 'high',
    ], $agent['headers'])
        ->assertStatus(201)
        ->assertJsonPath('data.subject', 'New API Ticket')
        ->assertJsonPath('message', 'Ticket created.');
});

it('replies to a ticket', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00200']);

    $this->postJson('/support/api/v1/tickets/ESC-00200/reply', [
        'body' => 'This is a reply via API.',
    ], $agent['headers'])
        ->assertStatus(201)
        ->assertJsonPath('data.body', 'This is a reply via API.')
        ->assertJsonPath('data.is_internal_note', false);
});

it('adds internal note', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00201']);

    $this->postJson('/support/api/v1/tickets/ESC-00201/reply', [
        'body' => 'Internal note via API.',
        'is_internal_note' => true,
    ], $agent['headers'])
        ->assertStatus(201)
        ->assertJsonPath('data.is_internal_note', true);
});

it('changes ticket status', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00300']);

    $this->patchJson('/support/api/v1/tickets/ESC-00300/status', [
        'status' => 'in_progress',
    ], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');
});

it('changes ticket priority', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00400']);

    $this->patchJson('/support/api/v1/tickets/ESC-00400/priority', [
        'priority' => 'urgent',
    ], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('priority', 'urgent');
});

it('assigns ticket to agent', function () {
    $agent = apiAgent();
    $otherAgent = TestUser::create([
        'name' => 'Other Agent',
        'email' => 'other-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'is_agent' => true,
    ]);
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00500']);

    $this->postJson('/support/api/v1/tickets/ESC-00500/assign', [
        'agent_id' => $otherAgent->getKey(),
    ], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('message', 'Ticket assigned.');
});

it('toggles follow on ticket', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00600']);

    $this->postJson('/support/api/v1/tickets/ESC-00600/follow', [], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('following', true);

    $this->postJson('/support/api/v1/tickets/ESC-00600/follow', [], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('following', false);
});

it('soft deletes a ticket', function () {
    $agent = apiAgent();
    $ticket = createTicket($agent['user'], ['reference' => 'ESC-00700']);

    $this->deleteJson('/support/api/v1/tickets/ESC-00700', [], $agent['headers'])
        ->assertOk()
        ->assertJsonPath('message', 'Ticket deleted.');

    expect(Ticket::where('reference', 'ESC-00700')->first())->toBeNull();
    expect(Ticket::withTrashed()->where('reference', 'ESC-00700')->first())->not->toBeNull();
});

it('gets dashboard stats', function () {
    $agent = apiAgent();
    createTicket($agent['user']);

    $this->getJson('/support/api/v1/dashboard', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure([
            'stats' => ['open', 'my_assigned', 'unassigned', 'sla_breached', 'resolved_today'],
            'recent_tickets',
            'needs_attention',
            'my_performance',
        ]);
});

it('lists agents', function () {
    $agent = apiAgent();

    $this->getJson('/support/api/v1/agents', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists departments', function () {
    $agent = apiAgent();

    $this->getJson('/support/api/v1/departments', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('lists tags', function () {
    $agent = apiAgent();

    $this->getJson('/support/api/v1/tags', $agent['headers'])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns realtime config', function () {
    $agent = apiAgent();

    $this->getJson('/support/api/v1/realtime/config', $agent['headers'])
        ->assertOk();
});
