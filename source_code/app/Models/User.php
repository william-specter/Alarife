<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_platform_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_platform_admin' => 'boolean',
        ];
    }

    public function encyclopedias()
    {
        return $this->hasMany(Encyclopedia::class, 'owner_id');
    }

    public function memberships()
    {
        return $this->hasMany(EncyclopediaMember::class);
    }

    public function accessibleEncyclopedias()
    {
        $query = Encyclopedia::query()->withCount(['topics', 'articles', 'publications']);

        if (! $this->is_platform_admin) {
            $query->where(function ($query) {
                $query->where('owner_id', $this->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $this->id));
            });
        }

        return $query->latest('updated_at');
    }
}
