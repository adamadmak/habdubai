<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * "Contains, ignoring case" on any database. LIKE is case-sensitive on
 * PostgreSQL (production) and not on SQLite (the tests), so both sides are
 * lowercased; % and _ in the search are taken literally.
 */
final class Search
{
    /** @param array<int, string> $columns */
    public static function anyColumn(Builder $query, array $columns, string $term): Builder
    {
        $term = mb_strtolower(trim($term));
        if ($term === '') {
            return $query;
        }

        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $q) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $q->orWhereRaw('lower('.$q->getQuery()->getGrammar()->wrap($column).") like ? escape '\\'", [$pattern]);
            }
        });
    }
}
