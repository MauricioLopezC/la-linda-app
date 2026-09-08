<?php

use App\Providers\AppServiceProvider;

function resolveDestructiveGuard(): bool
{
    $provider = new AppServiceProvider(app());

    $method = new ReflectionMethod($provider, 'shouldProhibitDestructiveCommands');

    return $method->invoke($provider);
}

it('defaults the destructive commands flag to false', function () {
    expect(config('database.allow_destructive_commands'))->toBeFalse();
});

it('prohibits destructive commands in production by default', function () {
    app()['env'] = 'production';
    config(['database.allow_destructive_commands' => false]);

    expect(resolveDestructiveGuard())->toBeTrue();
});

it('allows destructive commands in production when the flag is enabled', function () {
    app()['env'] = 'production';
    config(['database.allow_destructive_commands' => true]);

    expect(resolveDestructiveGuard())->toBeFalse();
});

it('never prohibits destructive commands outside production', function () {
    app()['env'] = 'local';
    config(['database.allow_destructive_commands' => false]);

    expect(resolveDestructiveGuard())->toBeFalse();
});
