<?php

namespace App\Domain\Content;

use App\Models\Encyclopedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Slug
{
    public const RESERVED = [
        'dashboard', 'login', 'register', 'logout', 'forgot-password', 'reset-password',
        'verify-email', 'confirm-password', 'password', 'settings', 'author', 'encyclopedias',
        'themes', 'storage', 'build', 'api', 'up', 'admin', 'search', 'topics', 'articles',
    ];

    public static function make(string $value): string
    {
        return Str::slug($value, '-', 'en') ?: 'untitled';
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws ValidationException
     */
    public static function uniqueFor(string $model, int $encyclopediaId, string $value, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = self::base($value);

        if ($model === Encyclopedia::class) {
            $candidate = $base;
            $suffix = 2;
            while ($model::query()->where($column, $candidate)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
            ) {
                $candidate = $base.'-'.$suffix++;
            }

            return $candidate;
        }

        $candidate = $base;
        $suffix = 2;
        while ($model::query()
            ->where('encyclopedia_id', $encyclopediaId)
            ->where($column, $candidate)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
            if ($suffix > 200) {
                throw ValidationException::withMessages(['slug' => 'Could not generate a unique slug.']);
            }
        }

        return $candidate;
    }

    /** @throws ValidationException */
    public static function base(string $value): string
    {
        $base = self::make($value);
        if ($base === '') {
            $base = 'entry';
        }

        if (in_array($base, self::RESERVED, true)) {
            throw ValidationException::withMessages(['slug' => 'This URL slug is reserved.']);
        }

        return $base;
    }

    public static function assertReserved(string $slug): void
    {
        if (in_array($slug, self::RESERVED, true)) {
            throw ValidationException::withMessages(['slug' => 'This URL slug is reserved.']);
        }
    }
}
