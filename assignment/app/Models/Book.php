<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_DRAFT        = 'draft';
    const STATUS_SUBMITTED    = 'submitted';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_APPROVED     = 'approved';
    const STATUS_REJECTED     = 'rejected';
    const STATUS_PUBLISHED    = 'published';

    protected $fillable = [
        'author_id',
        'title',
        'description',
        'genre',
        'status',
        'reviewed_by',
        'published_by',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'published_at',
        'ai_summary',
        'ai_tags',
        'ai_reading_level',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
        'published_at' => 'datetime',
        'ai_tags'      => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BookVersion::class)->orderByDesc('version_number');
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function nextVersionNumber(): int
    {
        $latest = $this->versions()->max('version_number');
        return ($latest ?? 0) + 1;
    }
}