<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id','item_id','qty',
        'unit_price_estimated','unit_price_real',
        'subtotal_estimated','subtotal_real',
    ];

    public function batch()
    {
        return $this->belongsTo(PurchaseBatch::class, 'purchase_id');
    }
}
