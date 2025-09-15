<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Trail extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'status',
        'description',
        'cover_url',
        'created_by',
    ];

    protected static function booted()
    {
        static::saving(function (Trail $trail) {
            if (empty($trail->slug)) {
                $trail->slug = Str::slug($trail->title);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contents()
    {
        return $this->belongsToMany(ContentItem::class, 'trail_items')
            ->withPivot(['order', 'required', 'note'])
            ->withTimestamps()
            ->orderBy('trail_items.order');
    }

    public function items()
    {
        return $this->hasMany(TrailItem::class)->orderBy('order');
    }
}
