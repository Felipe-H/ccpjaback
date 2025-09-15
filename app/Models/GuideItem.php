<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuideItem extends Model
{
    // Tabela padrão já é "guide_items"
    protected $fillable = [
        'guide_id', 'item_id', 'purpose', 'default_qty', 'unit', 'required', 'notes',
    ];

    protected $casts = [
        'guide_id' => 'integer',
        'item_id' => 'integer',
        'default_qty' => 'float',
        'required' => 'boolean',
    ];

    public function guide()
    {
        return $this->belongsTo(Guide::class, 'guide_id');
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
