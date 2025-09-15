<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    protected $fillable = [
        'user_id', 'name', 'nickname', 'line_id', 'photo_url',
        'salutation', 'started_at', 'notes', 'active',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'line_id' => 'integer',
        'active' => 'boolean',
        'started_at' => 'datetime',
    ];

    /* RELACIONAMENTOS */
    public function line()
    {
        return $this->belongsTo(SpiritualLine::class, 'line_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guideItems()
    {
        return $this->hasMany(GuideItem::class, 'guide_id');
    }

    public function items()
    {
        return $this->belongsToMany(InventoryItem::class, 'guide_items', 'guide_id', 'item_id')
            ->withPivot(['purpose', 'default_qty', 'unit', 'required', 'notes'])
            ->withTimestamps();
    }

    /* SCOPES */
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
}
