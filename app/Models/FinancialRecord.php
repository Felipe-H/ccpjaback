<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'category',
        'description',
        'amount',
        'amount_estimated',
        'date',
        'payment_status',
        'user_id',
        'item_id',
        'event_id',
        'purchase_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'date' => 'date',
    ];
}
