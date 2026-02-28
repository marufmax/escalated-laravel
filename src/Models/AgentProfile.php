<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentProfile extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('agent_profiles');
    }

    protected function casts(): array
    {
        return [
            'max_tickets' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Escalated::userModel(), 'user_id');
    }

    public function isLightAgent(): bool
    {
        return $this->agent_type === 'light';
    }

    public function isFullAgent(): bool
    {
        return $this->agent_type === 'full';
    }

    /**
     * Get or create a profile for a user.
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['agent_type' => 'full']
        );
    }
}
