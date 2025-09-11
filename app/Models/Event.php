<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
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
}
