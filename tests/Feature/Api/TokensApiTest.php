<?php

namespace Tests\Feature\Api;

use App\Models\AuditoriaAcceso;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Pantalla de tokens de la API pública (Administrador de sistema).
 */
class TokensApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->admin = User::factory()->create()->assignRole('Administrador de sistema');
    }

    public function test_emite_un_token_que_se_muestra_una_sola_vez_y_funciona(): void
    {
        $duenio = User::factory()->create(['username' => 'datosabiertos']);

        $this->actingAs($this->admin)
            ->post(route('tokens.store'), ['nombre' => 'Portal', 'user_id' => $duenio->id, 'dias' => 30])
            ->assertRedirect(route('tokens.index'))
            ->assertSessionHas('tokenNuevo');

        $token = $duenio->tokens()->sole();
        $this->assertSame(['datos-abiertos:leer'], $token->abilities);
        $this->assertTrue($token->expires_at->isSameDay(now()->addDays(30)));
        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_TOKEN_ALTA,
            'user_id' => $this->admin->id,
        ]);

        $textoPlano = session('tokenNuevo')['token'];

        // Llega a la pantalla una vez (flash)…
        $this->actingAs($this->admin)
            ->get(route('tokens.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('TokensApi/Index')
                ->where('tokenNuevo.token', $textoPlano)
                ->has('tokens', 1)
                ->where('tokens.0.duenio', $duenio->name));

        // …y no vuelve a aparecer.
        $this->actingAs($this->admin)
            ->get(route('tokens.index'))
            ->assertInertia(fn (Assert $page) => $page->where('tokenNuevo', null));

        // El token sirve para la API pública.
        $this->app['auth']->forgetGuards();
        $this->withToken($textoPlano)->getJson('/api/v1/eventos')->assertOk();
    }

    public function test_sin_dias_el_token_no_vence(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tokens.store'), ['nombre' => 'Sin vencimiento', 'user_id' => $this->admin->id])
            ->assertRedirect(route('tokens.index'));

        $this->assertNull($this->admin->tokens()->sole()->expires_at);
    }

    public function test_valida_los_datos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('tokens.store'), ['nombre' => '', 'user_id' => 999, 'dias' => 0])
            ->assertSessionHasErrors(['nombre', 'user_id', 'dias']);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_revoca_un_token_y_lo_audita(): void
    {
        $token = $this->admin->createToken('Portal', ['datos-abiertos:leer'])->accessToken;

        $this->actingAs($this->admin)
            ->delete(route('tokens.destroy', $token->id))
            ->assertRedirect(route('tokens.index'));

        $this->assertModelMissing($token);
        $this->assertDatabaseHas('auditoria_accesos', [
            'evento' => AuditoriaAcceso::EVENTO_TOKEN_BAJA,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_solo_gestiona_los_tokens_de_la_api_publica(): void
    {
        $otro = $this->admin->createToken('Otro uso', ['otra-cosa'])->accessToken;

        $this->actingAs($this->admin)
            ->get(route('tokens.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tokens', 0));

        $this->actingAs($this->admin)
            ->delete(route('tokens.destroy', $otro->id))
            ->assertNotFound();

        $this->assertModelExists($otro);
    }

    public function test_solo_el_administrador_de_sistema_accede(): void
    {
        $administrativo = User::factory()->create()->assignRole('Administrativo');

        $this->actingAs($administrativo)->get(route('tokens.index'))->assertForbidden();
        $this->actingAs($administrativo)
            ->post(route('tokens.store'), ['nombre' => 'X', 'user_id' => $administrativo->id])
            ->assertForbidden();
    }
}
