<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrailItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'trail_id',
        'content_item_id',
        'order',
        'required',
        'note',
    ];

    protected $casts = [
        'order' => 'integer',
        'required' => 'boolean',
    ];

    public function trail()
    {
        return $this->belongsTo(Trail::class);
    }

    public function content()
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }
}
