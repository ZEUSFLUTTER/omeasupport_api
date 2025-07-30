<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type_probleme',
        'description',
        'adresse',
        'photos',
        'statut',
        'date_rdv',
    ];

    protected $casts = [
        'photos' => 'array',
        'date_rdv' => 'datetime',
    ];

    /**
     * L'utilisateur (client) qui a créé le ticket
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
