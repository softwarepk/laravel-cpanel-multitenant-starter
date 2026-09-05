<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();
        $user->forceFill(['password' => $input['password']])->save();
    }
}
