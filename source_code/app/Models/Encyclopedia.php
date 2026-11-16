<?php

namespace App\Models;

use Database\Factories\EncyclopediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encyclopedia extends Model
{
    /** @use HasFactory<EncyclopediaFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'language',
        'visibility',
        'status',
        'theme_key',
        'theme_version',
        'theme_settings',
        'cover_style',
        'export_enabled',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme_settings' => 'array',
            'export_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EncyclopediaMember::class);
    }

    public function covers(): HasMany
    {
        return $this->hasMany(EncyclopediaCover::class)->orderBy('position');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(EncyclopediaExport::class);
    }

    public function crossReferences(): HasMany
    {
        return $this->hasMany(CrossReference::class, 'source_encyclopedia_id')->orderBy('sort_order');
    }

    public function incomingReferences(): HasMany
    {
        return $this->hasMany(CrossReference::class, 'target_encyclopedia_id');
    }

    public function latestPublication(): ?Publication
    {
        return $this->publications()->latest('version')->first();
    }

    public function membershipRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($user->id === $this->owner_id) {
            return 'owner';
        }

        return $this->members->firstWhere('user_id', $user->id)?->role;
    }

    public function isMember(?User $user): bool
    {
        return $this->membershipRole($user) !== null;
    }

    public function isPubliclyAccessible(): bool
    {
        return $this->status === 'published' && $this->visibility === 'public';
    }

    public function isLinkAccessible(): bool
    {
        return $this->status === 'published' && in_array($this->visibility, ['public', 'unlisted'], true);
    }

    public function isAccessibleBy(?User $user): bool
    {
        if ($user?->is_platform_admin) {
            return true;
        }

        if ($this->isLinkAccessible()) {
            return true;
        }

        return $this->isMember($user);
    }
}
