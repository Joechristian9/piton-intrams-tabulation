<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate with their email', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('judges can authenticate with their username', function () {
    $user = User::factory()->create(['role' => 'judge', 'username' => 'pageant26-judge1']);

    $this->post('/login', [
        'login' => 'pageant26-judge1',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create(['username' => 'someone']);

    $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('login');
    $this->post('/login', ['login' => 'someone', 'password' => 'wrong-password'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('too many failed attempts are throttled', function () {
    User::factory()->create(['username' => 'someone']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['login' => 'someone', 'password' => 'wrong-password']);
    }

    $this->post('/login', ['login' => 'someone', 'password' => 'password'])
        ->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toStartWith('Too many login attempts.');
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
