<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table    = 'knowledge_base';
    protected $fillable = [
        'title','content','category','tags',
        'author_id','is_published','views','rating','rating_count',
    ];

    protected $casts = [
        'tags'         => 'array',
        'is_published' => 'boolean',
        'rating'       => 'decimal:2',
    ];

    public function author() { return $this->belongsTo(User::class, 'author_id'); }

    public function scopePublished($q) { return $q->where('is_published', true); }

    public function incrementViews(): void {
        $this->increment('views');
    }

    public function addRating(int $score): void {
        $total = ($this->rating * $this->rating_count) + $score;
        $count = $this->rating_count + 1;
        $this->update(['rating' => round($total / $count, 2), 'rating_count' => $count]);
    }
}
