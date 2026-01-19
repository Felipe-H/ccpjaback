<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPending extends Model
{
    protected $fillable = [
        'event_id',
        'title',
        'description',
        'status',
        'assignee_id',
        'created_by',
        'due_date',
        'is_private',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'is_private' => 'boolean',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
