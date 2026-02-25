<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TicketLinkController extends Controller
{
    /**
     * Get all links for a ticket.
     */
    public function index(Ticket $ticket): JsonResponse
    {
        $asParent = TicketLink::where('parent_ticket_id', $ticket->id)
            ->with('childTicket:id,reference,subject,status,type')
            ->get();

        $asChild = TicketLink::where('child_ticket_id', $ticket->id)
            ->with('parentTicket:id,reference,subject,status,type')
            ->get();

        $links = [];

        foreach ($asParent as $link) {
            $links[] = [
                'id' => $link->id,
                'link_type' => $link->link_type,
                'direction' => 'parent',
                'ticket' => $link->childTicket,
            ];
        }

        foreach ($asChild as $link) {
            $links[] = [
                'id' => $link->id,
                'link_type' => $link->link_type,
                'direction' => 'child',
                'ticket' => $link->parentTicket,
            ];
        }

        return response()->json(['links' => $links]);
    }

    /**
     * Create a link between two tickets.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'target_reference' => 'required|string',
            'link_type' => 'required|string|in:problem_incident,parent_child,related',
        ]);

        $target = Ticket::where('reference', $request->input('target_reference'))->firstOrFail();

        if ($target->id === $ticket->id) {
            return back()->with('error', 'Cannot link a ticket to itself.');
        }

        // Prevent duplicate links
        $exists = TicketLink::where(function ($q) use ($ticket, $target) {
            $q->where('parent_ticket_id', $ticket->id)->where('child_ticket_id', $target->id);
        })->orWhere(function ($q) use ($ticket, $target) {
            $q->where('parent_ticket_id', $target->id)->where('child_ticket_id', $ticket->id);
        })->where('link_type', $request->input('link_type'))->exists();

        if ($exists) {
            return back()->with('error', 'These tickets are already linked.');
        }

        TicketLink::create([
            'parent_ticket_id' => $ticket->id,
            'child_ticket_id' => $target->id,
            'link_type' => $request->input('link_type'),
        ]);

        return back()->with('success', 'Ticket linked successfully.');
    }

    /**
     * Remove a link.
     */
    public function destroy(Ticket $ticket, TicketLink $link): RedirectResponse
    {
        $link->delete();

        return back()->with('success', 'Ticket link removed.');
    }
}
