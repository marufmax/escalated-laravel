<?php

namespace Escalated\Laravel\Services;

use Illuminate\Database\Eloquent\Model;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\EscalatedManager;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketService
{
    public function __construct(protected EscalatedManager $manager) {}

    public function create(Model $requester, array $data): Ticket
    {
        return $this->manager->driver()->createTicket($requester, $data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        return $this->manager->driver()->updateTicket($ticket, $data);
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, ?Model $causer = null): Ticket
    {
        return $this->manager->driver()->transitionStatus($ticket, $status, $causer);
    }

    public function reply(Ticket $ticket, Model $author, string $body, array $attachments = []): Reply
    {
        return $this->manager->driver()->addReply($ticket, $author, $body, false, $attachments);
    }

    public function addNote(Ticket $ticket, Model $author, string $body, array $attachments = []): Reply
    {
        return $this->manager->driver()->addReply($ticket, $author, $body, true, $attachments);
    }

    public function find(int|string $id): Ticket
    {
        return $this->manager->driver()->getTicket($id);
    }

    public function list(array $filters = [], ?Model $for = null): LengthAwarePaginator
    {
        return $this->manager->driver()->listTickets($filters, $for);
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Model $causer = null): Ticket
    {
        return $this->manager->driver()->changePriority($ticket, $priority, $causer);
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Model $causer = null): Ticket
    {
        return $this->manager->driver()->addTags($ticket, $tagIds, $causer);
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Model $causer = null): Ticket
    {
        return $this->manager->driver()->removeTags($ticket, $tagIds, $causer);
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Model $causer = null): Ticket
    {
        return $this->manager->driver()->changeDepartment($ticket, $departmentId, $causer);
    }

    public function close(Ticket $ticket, ?Model $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Closed, $causer);
    }

    public function resolve(Ticket $ticket, ?Model $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Resolved, $causer);
    }

    public function reopen(Ticket $ticket, ?Model $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Reopened, $causer);
    }
}
