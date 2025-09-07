<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name','quantity','price','description','category',
        'purchase_type','status','date_added','user_id'
    ];
}
