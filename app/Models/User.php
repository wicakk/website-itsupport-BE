<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    public function requestedTickets() {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets() {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function comments() {
        return $this->hasMany(TicketComment::class);
    }

    public function assets() {
        return $this->hasMany(Asset::class, 'assigned_to');
    }

    public function articles() {
        return $this->hasMany(KnowledgeBase::class, 'author_id');
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
            'super_admin'  => 'Super Admin',
            'manager_it'   => 'Manager IT',
            'it_support'   => 'IT Support',
            default        => 'User',
        };
    }
}
