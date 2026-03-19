<?php
// app/Models/Project.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'category', 'priority', 'color', 'status',
        'start_date', 'due_date', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date'   => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(TaskColumn::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // ← Relasi attachment project
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class)->latest();
    }
}
