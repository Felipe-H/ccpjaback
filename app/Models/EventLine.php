<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventLine extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'event_id', 'line_id', 'role',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'line_id' => 'integer',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function line()
    {
        return $this->belongsTo(SpiritualLine::class, 'line_id');
    }
}
