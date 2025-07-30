<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rapport extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'client_id',
        'technicien_id',
        'duree',
        'solution',
        'prix',
        'statut',
        'date_intervention',
    ];
}
