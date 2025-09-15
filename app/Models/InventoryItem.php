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

    public function lineTemplates()
    {
        return $this->hasMany(LineItemTemplate::class, 'item_id');
    }

    public function guideItems()
    {
        return $this->hasMany(GuideItem::class, 'item_id');
    }

    public function guides()
    {
        return $this->belongsToMany(Guide::class, 'guide_items', 'item_id', 'guide_id')
            ->withPivot(['purpose','default_qty','unit','required','notes'])
            ->withTimestamps();
    }

    public function lines()
    {
        return $this->belongsToMany(
            \App\Models\SpiritualLine::class,
            'line_item_templates',
            'item_id',
            'line_id'
        )->withPivot(['purpose','suggested_qty','unit','required']);
    }
}
