<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'client_id', 'technician_id',
        'heure_debut', 'heure_fin', 'duree', 'date_prise_en_charge',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Le client est un user ayant role = client
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
