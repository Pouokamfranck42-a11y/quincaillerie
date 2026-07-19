<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trace structurée des exceptions inattendues (voir bootstrap/app.php pour ce qui est
 * journalisé ici vs simplement écrit dans storage/logs/laravel.log). Sert à la fois de
 * "logs consultables" (écran d'administration) et de source pour l'alerte email des
 * erreurs critiques — Phase 1, supervision minimale.
 */
class ErrorLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['exception_class', 'message', 'file', 'line', 'url', 'method', 'user_id', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public static function recordFrom(\Throwable $e, ?\Illuminate\Http\Request $request = null): self
    {
        return self::create([
            'exception_class' => $e::class,
            'message' => mb_substr($e->getMessage(), 0, 2000),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'user_id' => $request?->user()?->id,
            'created_at' => now(),
        ]);
    }
}
