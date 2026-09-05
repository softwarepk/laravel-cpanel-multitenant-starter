<?php

use App\Models\User;

it('redirects guests to the tenant login page', function (): void {
    $this->get('/dashboard')->assertRedirectToRoute('login');
});

it('serves the dashboard to an authenticated tenant user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
