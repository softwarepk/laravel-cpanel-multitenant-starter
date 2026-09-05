<?php

namespace App\Livewire\Settings;

use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Security settings')]
class Security extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);
        auth()->user()->update(['password' => $validated['password']]);
        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('status', 'Password updated.');
    }

    public function render()
    {
        return view('settings.security');
    }
}
