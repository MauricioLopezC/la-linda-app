<?php

use App\Rules\Purchasing\ValidCuit;

test('valid cuits pass validation with or without hyphens', function (string $cuit) {
    $rule = new ValidCuit;
    $failed = false;

    $rule->validate('tax_id', $cuit, function () use (&$failed) {
        $failed = true;
    });

    expect($failed)->toBeFalse();
})->with([
    '30-50085862-8',
    '30500858628',
    '30-50279317-5',
    '30502793175',
    '30-50051184-9',
    '30500511849',
    '30-50094946-1',
    '30500949461',
    '20-28945612-1',
    '20289456121',
]);

test('invalid cuits fail validation', function (string $invalidCuit, string $expectedErrorSubstring) {
    $rule = new ValidCuit;
    $errorMessage = null;

    $rule->validate('tax_id', $invalidCuit, function (string $message) use (&$errorMessage) {
        $errorMessage = $message;
    });

    expect($errorMessage)->not->toBeNull()
        ->and($errorMessage)->toContain($expectedErrorSubstring);
})->with([
    ['123', 'exactamente 11 dígitos'],
    ['3050085862899', 'exactamente 11 dígitos'],
    ['40-50085862-8', 'prefijo fiscal inválido'],
    ['10-50085862-8', 'prefijo fiscal inválido'],
    ['30-50085862-0', 'dígito verificador incorrecto'],
    ['30-50279317-0', 'dígito verificador incorrecto'],
    ['abc-defghijk-l', 'exactamente 11 dígitos'],
]);

test('cuit sanitization and formatting helpers work properly', function () {
    expect(ValidCuit::sanitize(' 30-50085862-8 '))->toBe('30500858628')
        ->and(ValidCuit::format('30500858628'))->toBe('30-50085862-8')
        ->and(ValidCuit::format('30-50085862-8'))->toBe('30-50085862-8')
        ->and(ValidCuit::format(null))->toBeNull()
        ->and(ValidCuit::format('123'))->toBe('123');
});
