<?php

namespace Tests\Feature\Parametros;

use App\Jobs\EnviarNotificacionWhatsapp;
use App\Models\AuditoriaAcceso;
use App\Models\Notificacion;
use App\Models\Parametro;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-029: parámetros generales del sistema.
 */
class ParametrosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->admin = User::factory()->create()->assignRole('Administrador de sistema');
    }

    /**
     * @return array<string, int>
     */
    private function valores(array $cambios = []): array
    {
        return [...Parametro::vigentes(), ...$cambios];
    }

    public function test_sin_configurar_rigen_los_valores_por_defecto(): void
    {
        $this->assertSame([
            Parametro::SESION_MINUTOS => 30,
            Parametro::LOGIN_MAX_INTENTOS => 3,
            Parametro::BLOQUEO_MINUTOS => 15,
            Parametro::REFRESCO_DASHBOARDS => 60,
            Parametro::NOTIF_REINTENTOS => 3,
            Parametro::NOTIF_INTERVALO => 30,
        ], Parametro::vigentes());
    }

    public function test_solo_el_administrador_de_sistema_accede(): void
    {
        foreach (['Supervisor', 'Administrativo'] as $rol) {
            $this->actingAs(User::factory()->create()->assignRole($rol))
                ->get(route('parametros.index'))
                ->assertForbidden();
        }

        $this->actingAs($this->admin)
            ->get(route('parametros.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Parametros/Index')
                ->where('valores.'.Parametro::SESION_MINUTOS, 30)
                ->has('definiciones', 6));
    }

    public function test_guarda_los_cambios_y_audita_valor_anterior_y_nuevo(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametros.update'), $this->valores([Parametro::BLOQUEO_MINUTOS => 20]))
            ->assertSessionHas('success');

        $this->assertSame(20, Parametro::valor(Parametro::BLOQUEO_MINUTOS));
        $this->assertSame(1, Parametro::count()); // solo se persiste lo que cambió
        $this->assertSame(
            'Tiempo de bloqueo de cuenta: 15 → 20 minutos',
            AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_PARAMETROS)->value('descripcion'),
        );
    }

    public function test_sin_cambios_no_audita(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametros.update'), $this->valores())
            ->assertSessionHas('success');

        $this->assertFalse(AuditoriaAcceso::where('evento', AuditoriaAcceso::EVENTO_PARAMETROS)->exists());
    }

    public function test_valida_el_rango_de_cada_parametro(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametros.update'), $this->valores([
                Parametro::LOGIN_MAX_INTENTOS => 0,
                Parametro::REFRESCO_DASHBOARDS => 'mucho',
            ]))
            ->assertSessionHasErrors([Parametro::LOGIN_MAX_INTENTOS, Parametro::REFRESCO_DASHBOARDS]);
    }

    // --- Aplicación de cada parámetro -------------------------------------------

    public function test_el_bloqueo_usa_los_intentos_y_minutos_configurados(): void
    {
        Parametro::create(['clave' => Parametro::LOGIN_MAX_INTENTOS, 'valor' => 2]);
        Parametro::create(['clave' => Parametro::BLOQUEO_MINUTOS, 'valor' => 5]);
        $user = User::factory()->create(['username' => 'operador']);

        foreach (range(1, 2) as $_) {
            $this->post('/login', ['username' => 'operador', 'password' => 'incorrecta']);
        }

        $user->refresh();
        $this->assertNotNull($user->bloqueado_hasta);
        $this->assertEqualsWithDelta(5 * 60, now()->diffInSeconds($user->bloqueado_hasta), 5);
    }

    public function test_la_sesion_toma_la_duracion_configurada(): void
    {
        Parametro::create(['clave' => Parametro::SESION_MINUTOS, 'valor' => 45]);

        $this->get('/login')->assertOk();

        $this->assertSame(45, config('session.lifetime'));
    }

    public function test_el_refresco_de_dashboards_usa_el_intervalo_configurado(): void
    {
        Parametro::create(['clave' => Parametro::REFRESCO_DASHBOARDS, 'valor' => 120]);

        $this->actingAs($this->admin)
            ->get(route('analisis.index'))
            ->assertInertia(fn (Assert $page) => $page->where('intervaloRefresco', 120));
    }

    public function test_el_job_de_notificacion_usa_los_reintentos_configurados(): void
    {
        $this->actingAs($this->admin)
            ->put(route('parametros.update'), $this->valores([
                Parametro::NOTIF_REINTENTOS => 5,
                Parametro::NOTIF_INTERVALO => 10,
            ]));

        $job = new EnviarNotificacionWhatsapp(new Notificacion);

        $this->assertSame(6, $job->tries());
        $this->assertSame(10, $job->backoff());
    }
}
