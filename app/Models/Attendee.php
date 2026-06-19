<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    protected $fillable = ['event_id', 'name', 'email', 'registered_at'];

    protected $casts = [
        'registered_at' => 'datetime',
        'reminded_3day_at' => 'datetime',
        'reminded_24h_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
