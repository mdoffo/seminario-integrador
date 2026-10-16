<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_un_token_csrf_vencido_vuelve_a_la_pantalla_con_un_aviso(): void
    {
        // En los tests no se verifica el CSRF: se simula el 419 que da
        // VerifyCsrfToken cuando la sesión venció con la pantalla abierta.
        Route::post('/_test/csrf-vencido', fn () => throw new TokenMismatchException)->middleware('web');

        $this->from('/login')
            ->post('/_test/csrf-vencido')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'La sesión había expirado por inactividad. Volvé a intentarlo.');
    }

    public function test_un_token_csrf_vencido_en_la_api_sigue_siendo_419(): void
    {
        Route::post('/_test/csrf-vencido-json', fn () => throw new TokenMismatchException)->middleware('web');

        $this->postJson('/_test/csrf-vencido-json')->assertStatus(419);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_el_login_ignora_recordarme_para_respetar_la_expiracion_por_inactividad(): void
    {
        $user = User::factory()->create(['remember_token' => null]);

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticated();
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertEmpty($user->fresh()->getRememberToken());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
