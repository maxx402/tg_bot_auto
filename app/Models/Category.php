<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'type',
        'key',
        'title',
        'icon',
        'order',
        'status',
        'external_created_at',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'external_created_at' => 'datetime',
            'external_updated_at' => 'datetime',
        ];
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }
}
