<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'visibility',
        'title',
        'slug',
        'description',
        'source_url',
        'cover_url',
        'duration_sec',
        'created_by',
    ];

    protected $casts = [
        'duration_sec' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets()
    {
        return $this->hasMany(ContentAsset::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'content_item_tag')
            ->withTimestamps();
    }

    public function lines()
    {
        return $this->belongsToMany(SpiritualLine::class, 'content_item_line', 'content_item_id', 'line_id')
            ->withPivot(['role', 'is_primary', 'weight'])
            ->withTimestamps();
    }

    public function trails()
    {
        return $this->belongsToMany(Trail::class, 'trail_items')
            ->withPivot(['order', 'required', 'note'])
            ->withTimestamps()
            ->orderBy('trail_items.order');
    }
}
