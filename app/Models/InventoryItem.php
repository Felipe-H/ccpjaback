<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'quantity',
        'ideal_quantity',
        'price',
        'description',
        'category',
        'purchase_type',
        'priority',
        'status',
        'date_added',
        'user_id',
        'scope',
        'event_id',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'ideal_quantity' => 'integer',
        'price'          => 'decimal:2',
        'date_added'     => 'date:Y-m-d',
    ];

    protected $appends = ['qty_to_buy'];

    public function getQtyToBuyAttribute(): int
    {
        $ideal = (int) ($this->ideal_quantity ?? 0);
        $qty   = (int) ($this->quantity ?? 0);
        return max($ideal - $qty, 0);
    }

    public function eventLinks() {
        return $this->hasMany(EventItem::class, 'inventory_item_id');
    }

    public function events() {
        return $this->belongsToMany(Event::class, 'event_items')
            ->withPivot(['quantity_required','quantity_used','is_from_stock','is_ready','needed_by','notes'])
            ->withTimestamps();
    }
}
