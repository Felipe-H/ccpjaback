<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_item_id',
        'disk',
        'storage_path',
        'original_name',
        'mime',
        'size',
        'variant',
        'width',
        'height',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function content()
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }
}
