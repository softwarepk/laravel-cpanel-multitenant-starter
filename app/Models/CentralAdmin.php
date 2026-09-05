<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class CentralAdmin extends Authenticatable
{
    use Notifiable;

    public function getConnectionName(): ?string
    {
        return (string) config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
