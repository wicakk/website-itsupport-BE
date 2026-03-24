<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;  // ✅ tambah import
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'department', 'phone', 'avatar',
        'initials', 'color', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeTechnicians($query) {
        return $query->whereIn('role', ['it_support', 'manager_it']);
    }

    // ── Relations ────────────────────────────────────────────────────────────
    public function requestedTickets(): HasMany {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function comments(): HasMany {
        return $this->hasMany(TicketComment::class);
    }

    public function assets(): HasMany {
        return $this->hasMany(Asset::class, 'assigned_to');
    }

    public function articles(): HasMany {
        return $this->hasMany(KnowledgeBase::class, 'author_id');
    }

    /**
     * Project yang diikuti user sebagai member
     * Pivot: project_members (project_id, user_id)
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members', 'user_id', 'project_id');
    }

    /**
     * Task yang di-assign ke user via pivot task_assignees
     * ✅ FIX: many-to-many, bukan hasMany assigned_to
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees', 'user_id', 'task_id')
                    ->withTimestamps();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    public function isAdmin(): bool {
        return $this->role === 'super_admin';
    }

    public function isManagerIT(): bool {
        return in_array($this->role, ['super_admin', 'manager_it']);
    }

    public function isTechnician(): bool {
        return in_array($this->role, ['super_admin', 'manager_it', 'it_support']);
    }

    public function getRoleDisplayAttribute(): string {
        return match($this->role) {
            'super_admin' => 'Super Admin',
            'manager_it'  => 'Manager IT',
            'it_support'  => 'IT Support',
            default       => 'User',
        };
    }
}