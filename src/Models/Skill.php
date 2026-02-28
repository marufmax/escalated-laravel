<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Skill extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('skills');
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(
            Escalated::userModel(),
            Escalated::table('agent_skill'),
            'skill_id',
            'user_id'
        )->withPivot('proficiency');
    }

    protected static function booted(): void
    {
        static::creating(function (self $skill) {
            if (empty($skill->slug)) {
                $skill->slug = Str::slug($skill->name);
            }
        });
    }
}
