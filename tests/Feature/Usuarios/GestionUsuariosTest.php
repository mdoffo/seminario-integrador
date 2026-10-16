<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Gestión de usuarios y política de blanqueo de contraseñas.
 * Login por nombre de usuario; entre Administradores de sistema no se
 * blanquean la clave (recuperan por email).
 */
class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_el_listado_incluye_el_nombre_de_usuario(): void
    {
        // Nombres explicitos: el listado ordena por name.
        $admin = User::factory()->create([
            'name' => 'Ana Admin',
            'username' => 'admin.sistema',
        ])->assignRole('Administrador de sistema');

        User::factory()->create(['name' => 'Juan Perez', 'username' => 'jperez'])
            ->assignRole('Supervisor');

        $this->actingAs($admin)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Usuarios/Index')
                ->has('usuarios', 2)
                // La vista muestra una columna Usuario: sin este campo sale vacia.
                ->where('usuarios.1.username', 'jperez')
            );
    }

    public function test_el_login_es_por_usuario_no_por_email(): void
    {
        $user = User::factory()->create(['username' => 'jperez']);

        // Con el email como usuario, falla.
        $this->post('/login', ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Con el nombre de usuario, entra.
        $this->post('/login', ['username' => 'jperez', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_el_admin_de_sistema_blanquea_la_clave_de_otro_rol(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $hashViejo = $supervisor->password;

        $this->actingAs($admin)
            ->put(route('usuarios.update', $supervisor->id), [
                'name' => $supervisor->name,
                'username' => $supervisor->username,
                'email' => $supervisor->email,
                'password' => 'nueva-clave-123',
                'password_confirmation' => 'nueva-clave-123',
                'roles' => ['Supervisor'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotSame($hashViejo, $supervisor->fresh()->password);
    }

    public function test_no_se_blanquea_la_clave_de_un_admin_de_sistema(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $otro = User::factory()->create(['password' => Hash::make('clave-original')])
            ->assignRole('Administrador de sistema');
        $hashViejo = $otro->password;

        $this->actingAs($admin)
            ->put(route('usuarios.update', $otro->id), [
                'name' => $otro->name,
                'username' => $otro->username,
                'email' => $otro->email,
                'password' => 'intento-de-blanqueo',
                'password_confirmation' => 'intento-de-blanqueo',
                'roles' => ['Administrador de sistema'],
            ])
            ->assertSessionHas('error');

        // La contraseña no cambió.
        $this->assertSame($hashViejo, $otro->fresh()->password);
    }

    /**
     * PUT usuarios/{usuario} con los datos actuales del usuario y los cambios dados.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function actualizar(User $actor, User $usuario, array $cambios): TestResponse
    {
        return $this->actingAs($actor)->put(route('usuarios.update', $usuario->id), [
            'name' => $usuario->name,
            'username' => $usuario->username,
            'email' => $usuario->email,
            'roles' => $usuario->roles->pluck('name')->all(),
            ...$cambios,
        ]);
    }

    public function test_blanquear_la_clave_desbloquea_la_cuenta(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $supervisor->forceFill(['intentos_fallidos' => 3, 'bloqueado_hasta' => now()->addMinutes(15)])->save();

        $this->actualizar($admin, $supervisor, [
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])->assertSessionHasNoErrors();

        $supervisor->refresh();
        $this->assertSame(0, $supervisor->intentos_fallidos);
        $this->assertNull($supervisor->bloqueado_hasta);

        $this->post('/logout');
        $this->post('/login', ['username' => $supervisor->username, 'password' => 'nueva-clave-123']);
        $this->assertAuthenticatedAs($supervisor);
    }

    public function test_editar_sin_blanquear_no_desbloquea(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $supervisor = User::factory()->create()->assignRole('Supervisor');
        $supervisor->forceFill(['intentos_fallidos' => 3, 'bloqueado_hasta' => now()->addMinutes(15)])->save();

        $this->actualizar($admin, $supervisor, ['name' => 'Otro nombre'])->assertSessionHasNoErrors();

        $this->assertNotNull($supervisor->fresh()->bloqueado_hasta);
    }

    public function test_no_se_le_quita_el_rol_de_admin_a_otro_admin(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $otro = User::factory()->create()->assignRole('Administrador de sistema');

        $this->actualizar($admin, $otro, ['roles' => ['Administrativo']])->assertSessionHas('error');

        $this->assertTrue($otro->fresh()->hasRole('Administrador de sistema'));
    }

    public function test_a_otro_admin_se_le_pueden_sumar_roles(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $otro = User::factory()->create()->assignRole('Administrador de sistema');

        $this->actualizar($admin, $otro, ['roles' => ['Administrador de sistema', 'Supervisor']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($otro->fresh()->hasAllRoles(['Administrador de sistema', 'Supervisor']));
    }

    public function test_el_admin_no_puede_quitarse_a_si_mismo_el_rol(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');

        $this->actualizar($admin, $admin, ['roles' => ['Supervisor']])->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasRole('Administrador de sistema'));
    }

    public function test_un_usuario_puede_tener_varios_roles_y_no_quedar_sin_ninguno(): void
    {
        $admin = User::factory()->create()->assignRole('Administrador de sistema');
        $usuario = User::factory()->create()->assignRole('Supervisor');

        $this->actualizar($admin, $usuario, ['roles' => []])->assertSessionHasErrors('roles');

        $this->actualizar($admin, $usuario, ['roles' => ['Supervisor', 'Administrativo']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($usuario->fresh()->hasAllRoles(['Supervisor', 'Administrativo']));
    }

    public function test_la_ruta_de_cambio_rapido_de_roles_ya_no_existe(): void
    {
        $this->assertFalse(Route::has('usuarios.roles'));
    }
}
