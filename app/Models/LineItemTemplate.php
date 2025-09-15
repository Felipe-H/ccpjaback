<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineItemTemplate extends Model
{
    protected $fillable = [
        'line_id', 'item_id', 'purpose', 'suggested_qty', 'unit', 'required', 'notes',
    ];

    protected $casts = [
        'line_id' => 'integer',
        'item_id' => 'integer',
        'suggested_qty' => 'float',
        'required' => 'boolean',
    ];

    public function line()
    {
        return $this->belongsTo(SpiritualLine::class, 'line_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
