<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'dos_pasos_secreto',
    ];

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
            'dos_pasos_secreto' => 'encrypted',
            'dos_pasos_activado_en' => 'datetime',
        ];
    }

    // Tiene Google Authenticator confirmado: el login pide el codigo de 6 digitos.
    public function tieneDosPasos(): bool
    {
        return $this->dos_pasos_secreto !== null && $this->dos_pasos_activado_en !== null;
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class);
    }
}
