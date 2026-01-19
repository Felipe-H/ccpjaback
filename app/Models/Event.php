<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title','status','start_date','end_date','location','notes','created_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function items() {
        return $this->belongsToMany(InventoryItem::class, 'event_items')
            ->withPivot(['quantity_required','quantity_used','is_from_stock','is_ready','needed_by','notes'])
            ->withTimestamps();
    }

    public function eventItems() {
        return $this->hasMany(EventItem::class);
    }

    public function pendings() {
        return $this->hasMany(EventPending::class);
    }

    public function purchaseBatches() {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ...
    public function eventLines()
    {
        return $this->hasMany(EventLine::class, 'event_id');
    }

    public function lines()
    {
        return $this->belongsToMany(SpiritualLine::class, 'event_lines', 'event_id', 'line_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

}
