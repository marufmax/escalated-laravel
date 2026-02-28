<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\SideConversation;
use Escalated\Laravel\Models\SideConversationReply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SideConversationController extends Controller
{
    /**
     * List all side conversations for a ticket.
     */
    public function index(Ticket $ticket): JsonResponse
    {
        $conversations = SideConversation::where('ticket_id', $ticket->id)
            ->with(['replies.author', 'creator'])
            ->latest()
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    /**
     * Create a new side conversation.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'channel' => 'required|string|in:internal,email',
            'body' => 'required|string',
        ]);

        $conversation = SideConversation::create([
            'ticket_id' => $ticket->id,
            'subject' => $request->input('subject'),
            'channel' => $request->input('channel'),
            'status' => 'open',
            'created_by' => $request->user()?->getKey(),
        ]);

        // Create the first reply
        SideConversationReply::create([
            'side_conversation_id' => $conversation->id,
            'body' => $request->input('body'),
            'author_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', 'Side conversation created.');
    }

    /**
     * Add a reply to a side conversation.
     */
    public function reply(Request $request, Ticket $ticket, SideConversation $sideConversation): RedirectResponse
    {
        $request->validate([
            'body' => 'required|string',
        ]);

        SideConversationReply::create([
            'side_conversation_id' => $sideConversation->id,
            'body' => $request->input('body'),
            'author_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', 'Reply added.');
    }

    /**
     * Close a side conversation.
     */
    public function close(Ticket $ticket, SideConversation $sideConversation): RedirectResponse
    {
        $sideConversation->update(['status' => 'closed']);

        return back()->with('success', 'Side conversation closed.');
    }
}
