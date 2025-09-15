<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpiritualLine extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'parent_id', 'description',
        'icon', 'color_hex', 'meta', 'status', 'sort_order',
    ];

    protected $casts = [
        'meta' => 'array',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /* RELACIONAMENTOS */
    public function parent()
    {
        return $this->belongsTo(SpiritualLine::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(SpiritualLine::class, 'parent_id');
    }

    public function guides()
    {
        return $this->hasMany(Guide::class, 'line_id');
    }

    public function lineItemTemplates()
    {
        return $this->hasMany(LineItemTemplate::class, 'line_id');
    }

    public function eventLines()
    {
        return $this->hasMany(EventLine::class, 'line_id');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_lines', 'line_id', 'event_id');
    }

    /* SCOPES */
    public function scopeActive($q)
    {
        return $q->where('status', 'ativo');
    }
}
