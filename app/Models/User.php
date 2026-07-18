<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Nombre de comptes actifs (non supprimés) détenant actuellement une permission, directement
     * ou via un profil — garde-fou anti-verrouillage : ne jamais laisser ce nombre tomber à zéro
     * pour 'utilisateurs.permissions'.
     */
    public static function countActiveUsersWithPermission(string $permission): int
    {
        return self::permission($permission)->count();
    }

    /**
     * À appeler à l'intérieur d'une DB::transaction(), APRÈS avoir appliqué un changement
     * (retrait de permission, changement de profil, modification/suppression d'un profil,
     * suppression de compte), en lui passant le nombre de gestionnaires actifs constaté AVANT
     * ce changement (capturé par l'appelant avant d'appliquer quoi que ce soit). Lève une
     * exception (annule la transaction) uniquement si ce changement précis fait passer ce
     * nombre d'au moins 1 à 0 — ne bloque jamais une action sans rapport si l'invariant était
     * déjà rompu pour une autre raison (ex. base de test isolée sans compte de gestion créé).
     */
    public static function assertAtLeastOnePermissionManagerRemains(int $countBefore): void
    {
        if ($countBefore >= 1 && self::countActiveUsersWithPermission('utilisateurs.permissions') < 1) {
            throw ValidationException::withMessages([
                'permissions' => "Impossible : il doit toujours rester au moins un compte actif avec la permission « Gérer les profils et attribuer des permissions ». Attribuez-la à un autre compte avant de retirer ce droit ici.",
            ]);
        }
    }
}
