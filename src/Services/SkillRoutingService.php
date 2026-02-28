<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkillRoutingService
{
    /**
     * Find agents with skills matching ticket tags, sorted by current load.
     *
     * Maps ticket tags to skills by name, then finds agents
     * who have those skills, ordered by current open ticket count (ascending).
     */
    public function findMatchingAgents(Ticket $ticket): Collection
    {
        // Get the ticket's tag names
        $tagNames = $ticket->tags()->pluck('name')->toArray();

        if (empty($tagNames)) {
            return collect();
        }

        // Find skills that match tag names
        $skillIds = Skill::whereIn('name', $tagNames)->pluck('id')->toArray();

        if (empty($skillIds)) {
            return collect();
        }

        // Find agents with matching skills
        $agentSkillTable = Escalated::table('agent_skill');
        $ticketsTable = Escalated::table('tickets');

        $agents = DB::table($agentSkillTable)
            ->whereIn('skill_id', $skillIds)
            ->select('user_id')
            ->distinct()
            ->get()
            ->pluck('user_id');

        if ($agents->isEmpty()) {
            return collect();
        }

        // Load agents with their current open ticket count, sorted by load
        $userModel = Escalated::userModel();

        return $userModel::whereIn((new $userModel)->getKeyName(), $agents->toArray())
            ->withCount(['tickets as open_tickets_count' => function ($q) {
                $q->whereNotIn('status', ['resolved', 'closed']);
            }])
            ->orderBy('open_tickets_count', 'asc')
            ->get();
    }
}
