<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Requests\AssignTicketRequest;
use Escalated\Laravel\Http\Requests\ChangePriorityRequest;
use Escalated\Laravel\Http\Requests\ChangeStatusRequest;
use Escalated\Laravel\Http\Requests\ReplyToTicketRequest;
use Escalated\Laravel\Http\Requests\UpdateTagsRequest;
use Escalated\Laravel\Models\CannedResponse;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AdminTicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
    ) {}

    public function index(Request $request): Response
    {
        $tickets = $this->ticketService->list(
            $request->only(['status', 'priority', 'assigned_to', 'unassigned', 'department_id', 'search', 'sla_breached', 'tag_ids', 'sort_by', 'sort_dir', 'per_page'])
        );

        return Inertia::render('Escalated/Admin/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->all(),
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'agents' => $this->getAgents(),
        ]);
    }

    public function show(Ticket $ticket, Request $request): Response
    {
        $ticket->load([
            'replies' => fn ($q) => $q->with('author', 'attachments')->latest(),
            'attachments', 'tags', 'department', 'requester', 'assignee',
            'slaPolicy', 'activities' => fn ($q) => $q->with('causer')->latest()->take(20),
        ]);

        return Inertia::render('Escalated/Admin/Tickets/Show', [
            'ticket' => $ticket,
            'departments' => Department::active()->get(['id', 'name']),
            'tags' => Tag::all(['id', 'name', 'color']),
            'cannedResponses' => CannedResponse::forAgent($request->user()->getKey())->get(),
            'agents' => $this->getAgents(),
        ]);
    }

    public function reply(Ticket $ticket, ReplyToTicketRequest $request): RedirectResponse
    {
        $this->ticketService->reply($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []));

        return back()->with('success', 'Reply sent.');
    }

    public function note(Ticket $ticket, ReplyToTicketRequest $request): RedirectResponse
    {
        $this->ticketService->addNote($ticket, $request->user(), $request->validated('body'), $request->file('attachments', []));

        return back()->with('success', 'Note added.');
    }

    public function assign(Ticket $ticket, AssignTicketRequest $request): RedirectResponse
    {
        $this->assignmentService->assign($ticket, $request->validated('agent_id'), $request->user());

        return back()->with('success', 'Ticket assigned.');
    }

    public function status(Ticket $ticket, ChangeStatusRequest $request): RedirectResponse
    {
        $this->ticketService->changeStatus($ticket, TicketStatus::from($request->validated('status')), $request->user());

        return back()->with('success', 'Status updated.');
    }

    public function priority(Ticket $ticket, ChangePriorityRequest $request): RedirectResponse
    {
        $this->ticketService->changePriority($ticket, TicketPriority::from($request->validated('priority')), $request->user());

        return back()->with('success', 'Priority updated.');
    }

    public function tags(Ticket $ticket, UpdateTagsRequest $request): RedirectResponse
    {
        $newTagIds = collect($request->validated('tag_ids'))->map(fn ($id) => (int) $id);
        $currentTagIds = $ticket->tags()->pluck('id');

        $toAdd = $newTagIds->diff($currentTagIds)->values()->all();
        $toRemove = $currentTagIds->diff($newTagIds)->values()->all();

        if ($toAdd) {
            $this->ticketService->addTags($ticket, $toAdd, $request->user());
        }
        if ($toRemove) {
            $this->ticketService->removeTags($ticket, $toRemove, $request->user());
        }

        return back()->with('success', 'Tags updated.');
    }

    public function department(Ticket $ticket, Request $request): RedirectResponse
    {
        $request->validate(['department_id' => 'required|integer']);

        $this->ticketService->changeDepartment($ticket, $request->integer('department_id'), $request->user());

        return back()->with('success', 'Department updated.');
    }

    protected function getAgents(): array
    {
        $userModel = Escalated::userModel();
        $users = $userModel::all();

        return $users->filter(function ($user) {
            return (method_exists($user, 'escalated_agent') && $user->escalated_agent())
                || (method_exists($user, 'escalated_admin') && $user->escalated_admin());
        })->map(fn ($user) => [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
        ])->values()->all();
    }
}
