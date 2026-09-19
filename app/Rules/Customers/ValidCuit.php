<?php

namespace App\Rules\Customers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidCuit implements ValidationRule
{
    /**
     * @var list<int>
     */
    private const MULTIPLIERS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /**
     * @var list<string>
     */
    private const VALID_PREFIXES = ['20', '23', '24', '27', '30', '33', '34'];

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('El campo :attribute debe ser un CUIT válido.');

            return;
        }

        $cuit = static::sanitize((string) $value);

        if (strlen($cuit) !== 11 || ! ctype_digit($cuit)) {
            $fail('El CUIT debe contener exactamente 11 dígitos numéricos.');

            return;
        }

        $prefix = substr($cuit, 0, 2);
        if (! in_array($prefix, self::VALID_PREFIXES, true)) {
            $fail('El CUIT ingresado contiene un prefijo fiscal inválido.');

            return;
        }

        if (! self::isValidChecksum($cuit)) {
            $fail('El CUIT ingresado no es válido (dígito verificador incorrecto).');
        }
    }

    /**
     * Sanitize CUIT by removing any non-digit character.
     */
    public static function sanitize(?string $cuit): string
    {
        if ($cuit === null) {
            return '';
        }

        return (string) preg_replace('/\D/', '', $cuit);
    }

    /**
     * Format an 11-digit CUIT as XX-XXXXXXXX-X.
     */
    public static function format(?string $cuit): ?string
    {
        if ($cuit === null) {
            return null;
        }

        $digits = static::sanitize($cuit);

        if (strlen($digits) !== 11) {
            return $cuit;
        }

        return sprintf(
            '%s-%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 8),
            substr($digits, 10, 1)
        );
    }

    /**
     * Verify modulo 11 checksum for Argentine CUIT/CUIL.
     */
    private static function isValidChecksum(string $cuit): bool
    {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $cuit[$i]) * self::MULTIPLIERS[$i];
        }

        $remainder = $sum % 11;
        $calculatedDigit = match ($remainder) {
            0 => 0,
            1 => 9, // Caso borde AFIP: si el residuo es 1, se suele ajustar prefijo pero si llega aquí no coincide
            default => 11 - $remainder,
        };

        $actualDigit = (int) $cuit[10];

        return $calculatedDigit === $actualDigit;
    }
}
