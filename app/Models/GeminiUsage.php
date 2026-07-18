<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une ligne par jour — compteur d'appels Gemini pour le quota/coût (Phase 7). */
class GeminiUsage extends Model
{
    protected $fillable = ['date', 'calls'];

    protected $casts = [
        'date' => 'date',
    ];
}
