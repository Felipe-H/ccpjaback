<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventItem extends Model
{
    protected $fillable = [
        'event_id','inventory_item_id','quantity_required','quantity_used',
        'is_from_stock','is_ready','needed_by','notes',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
        'quantity_used'     => 'integer',
        'is_from_stock'     => 'boolean',
        'is_ready'          => 'boolean',
        'needed_by'         => 'date:Y-m-d',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function inventoryItem() {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
