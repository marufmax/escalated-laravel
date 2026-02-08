<?php

namespace Escalated\Laravel\Contracts;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface TicketDriver
{
    public function createTicket(Model $requester, array $data): Ticket;

    public function updateTicket(Ticket $ticket, array $data): Ticket;

    public function transitionStatus(Ticket $ticket, TicketStatus $status, ?Model $causer = null): Ticket;

    public function assignTicket(Ticket $ticket, int $agentId, ?Model $causer = null): Ticket;

    public function unassignTicket(Ticket $ticket, ?Model $causer = null): Ticket;

    public function addReply(Ticket $ticket, Model $author, string $body, bool $isNote = false, array $attachments = []): Reply;

    public function getTicket(int|string $id): Ticket;

    public function listTickets(array $filters = [], ?Model $for = null): LengthAwarePaginator;

    public function addTags(Ticket $ticket, array $tagIds, ?Model $causer = null): Ticket;

    public function removeTags(Ticket $ticket, array $tagIds, ?Model $causer = null): Ticket;

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Model $causer = null): Ticket;

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Model $causer = null): Ticket;
}
