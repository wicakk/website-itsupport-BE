<?php
// app/Models/TicketCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $fillable = [
        'name', 'color', 'description', 'is_active', 'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    // Scope aktif saja
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order')->orderBy('name');
    }

    // Relasi ke tiket
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category', 'name');
    }

    // Hitung jumlah tiket yang pakai kategori ini
    public function getTicketsCountAttribute(): int
    {
        return Ticket::where('category', $this->name)->count();
    }
}
