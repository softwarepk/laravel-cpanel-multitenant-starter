<?php

use App\Services\CentralSettings;
use Illuminate\Validation\Rules\Password;

it('keeps the starter password policy simple by default', function (): void {
    $settings = app(CentralSettings::class);

    expect($settings->passwordMinimumLength())->toBe(8)
        ->and($settings->passwordRequireMixedCase())->toBeFalse()
        ->and($settings->passwordRequireNumbers())->toBeFalse()
        ->and($settings->passwordRequireSymbols())->toBeFalse()
        ->and($settings->passwordRejectCompromised())->toBeFalse()
        ->and(validator(['password' => 'password'], ['password' => ['required', Password::default()]])->passes())->toBeTrue();
});

it('applies password policy changes from central settings without code changes', function (): void {
    $settings = app(CentralSettings::class);
    $settings->setMany([
        'password_min_length' => 8,
        'password_require_mixed_case' => true,
        'password_require_numbers' => true,
        'password_require_symbols' => true,
        'password_reject_compromised' => false,
    ]);

    expect(validator(['password' => 'password'], ['password' => ['required', Password::default()]])->fails())->toBeTrue()
        ->and(validator(['password' => 'Strong1!'], ['password' => ['required', Password::default()]])->passes())->toBeTrue()
        ->and($settings->passwordRequirements())->toContain('uppercase and lowercase letters', 'at least one number', 'at least one symbol');
});
