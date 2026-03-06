<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Content extends Model
{
    /** @use HasFactory<\Database\Factories\ContentFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'category_id',
        'type',
        'title',
        'cover',
        'content',
        'price',
        'views',
        'collects',
        'shares',
        'comments',
        'duration',
        'status',
        'member_data',
        'external_created_at',
        'external_updated_at',
        'sent_at',
        'send_attempts',
        'send_error',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'member_data' => 'array',
            'status' => 'boolean',
            'external_created_at' => 'datetime',
            'external_updated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function contentUrls(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->type === 'video') {
                    return is_string($this->content) ? [$this->content] : $this->content;
                }
                return is_array($this->content) ? $this->content : [$this->content];
            }
        );
    }
}
