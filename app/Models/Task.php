<?php
// app/Models/Task.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id','column_id','title','description','category',
        'priority','assigned_to','created_by','due_date','position',
    ];

    protected $casts = ['due_date' => 'datetime'];

    public function project(): BelongsTo   { return $this->belongsTo(Project::class); }
    public function column(): BelongsTo    { return $this->belongsTo(TaskColumn::class, 'column_id'); }
    public function assignee(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
    public function attachments(): HasMany { return $this->hasMany(TaskAttachment::class); }
    public function histories(): HasMany   { return $this->hasMany(TaskHistory::class)->latest(); }
    public function comments(): HasMany    { return $this->hasMany(TaskComment::class)->latest(); }

    /**
     * Multi-assignee via tabel pivot task_assignees
     * Satu task bisa di-assign ke banyak user
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'task_assignees',  // nama tabel pivot
            'task_id',         // foreign key task
            'user_id'          // foreign key user
        )
        ->select(['users.id', 'users.name', 'users.initials', 'users.color'])
        ->withTimestamps();
    }
}