<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class StatusController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Escalated/Admin/Statuses/Index', [
            'statuses' => TicketStatus::orderBy('category')->orderBy('position')->get(),
            'categories' => ['new', 'open', 'pending', 'on_hold', 'solved'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Escalated/Admin/Statuses/Form', [
            'categories' => ['new', 'open', 'pending', 'on_hold', 'solved'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category' => 'required|string|in:new,open,pending,on_hold,solved',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string',
            'position' => 'integer',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            TicketStatus::where('category', $validated['category'])->update(['is_default' => false]);
        }

        TicketStatus::create($validated);

        return redirect()->route('escalated.admin.statuses.index')
            ->with('success', 'Status created.');
    }

    public function edit(TicketStatus $status): Response
    {
        return Inertia::render('Escalated/Admin/Statuses/Form', [
            'status' => $status,
            'categories' => ['new', 'open', 'pending', 'on_hold', 'solved'],
        ]);
    }

    public function update(TicketStatus $status, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'category' => 'required|string|in:new,open,pending,on_hold,solved',
            'color' => 'required|string|max:20',
            'description' => 'nullable|string',
            'position' => 'integer',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            TicketStatus::where('category', $validated['category'])
                ->where('id', '!=', $status->id)
                ->update(['is_default' => false]);
        }

        $status->update($validated);

        return redirect()->route('escalated.admin.statuses.index')
            ->with('success', 'Status updated.');
    }

    public function destroy(TicketStatus $status): RedirectResponse
    {
        $status->delete();

        return redirect()->route('escalated.admin.statuses.index')
            ->with('success', 'Status deleted.');
    }
}
