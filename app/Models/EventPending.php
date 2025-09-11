<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPending extends Model
{
    protected $fillable = [
        'event_id','title','description','status','assignee_id','due_date',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function assignee() {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
