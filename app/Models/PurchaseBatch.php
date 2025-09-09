<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBatch extends Model
{
    protected $fillable = [
        'date','event_id','payment_status','notes',
        'total_estimated','total_real','created_by',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }
}
