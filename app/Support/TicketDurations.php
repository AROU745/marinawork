<?php

namespace App\Support;

class TicketDurations
{
    /**
     * Lots de tickets disponibles (clé => libellé).
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            '15min' => '15 min',
            '30min' => '30 min',
            '1h' => '1 h',
            '2h' => '2 h',
            '3h' => '3 h',
            '6h' => '6 h',
            '12h' => '12 h',
        ];
    }

    /**
     * Prix de vente en francs CFA.
     *
     * @return array<string, int>
     */
    public static function prices(): array
    {
        return [
            '15min' => 25,
            '30min' => 50,
            '1h' => 100,
            '2h' => 200,
            '3h' => 250,
            '6h' => 450,
            '12h' => 1000,
        ];
    }

    public static function price(?string $key): int
    {
        return self::prices()[$key] ?? 0;
    }

    public static function priceLabel(?string $key): string
    {
        $price = self::price($key);

        return $price > 0 ? '+'.$price.' F' : '—';
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $key): string
    {
        if ($key === null || $key === '') {
            return 'Non classé';
        }

        return self::all()[$key] ?? $key;
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::keys());
    }
}
