<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketHardwareAsset extends Model
{
    protected $fillable = [
        'ticket_id',
        'nama_aset',
        'kategori',
        'status',
        'brand',
        'model',
        'serial_number',
        'lokasi',
        'pengguna',
        'tgl_beli',
        'harga_beli',
        'garansi_sd',
        'catatan',
    ];

    protected $casts = [
        'tgl_beli'   => 'date',
        'garansi_sd' => 'date',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}