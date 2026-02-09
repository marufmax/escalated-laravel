<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Contracts\HasTickets;
use Escalated\Laravel\Contracts\Ticketable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TestUser extends Authenticatable implements Ticketable
{
    use HasFactory, HasTickets, Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'is_agent' => 'boolean',
        'is_admin' => 'boolean',
    ];

    public function getTicketableNameAttribute(): string
    {
        return $this->name;
    }

    public function getTicketableEmailAttribute(): string
    {
        return $this->email;
    }
}
