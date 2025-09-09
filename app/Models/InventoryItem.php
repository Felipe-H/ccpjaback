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
}
