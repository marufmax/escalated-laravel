<?php

namespace Escalated\Laravel\Database\Factories;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'reference' => sprintf('ESC-%05d', $counter),
            'requester_type' => null,
            'requester_id' => null,
            'subject' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'status' => TicketStatus::Open,
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'channel' => 'web',
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => TicketStatus::Open]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TicketStatus::InProgress]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    public function assigned(int $agentId = 1): static
    {
        return $this->state(['assigned_to' => $agentId]);
    }

    public function breachedSla(): static
    {
        return $this->state([
            'sla_first_response_breached' => true,
            'first_response_due_at' => now()->subHour(),
        ]);
    }

    public function withPriority(TicketPriority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function forRequester(string $type, int $id): static
    {
        return $this->state([
            'requester_type' => $type,
            'requester_id' => $id,
        ]);
    }
}
