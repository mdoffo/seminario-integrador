<?php

namespace Tests\Feature\Usuarios;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gestión de permisos por rol (crear permiso, asignar/quitar), reservada al
 * Administrador de sistema.
 */
class GestionPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Administrador de sistema');
    }

    public function test_el_admin_crea_un_permiso_nuevo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('roles.permisos.store'), ['name' => 'reportes.imprimir'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Permission::where('name', 'reportes.imprimir')->exists());
    }

    public function test_rechaza_un_permiso_con_formato_invalido(): void
    {
        $this->actingAs($this->admin())
            ->post(route('roles.permisos.store'), ['name' => 'Formato Invalido'])
            ->assertSessionHasErrors('name');
    }

    public function test_el_admin_asigna_y_quita_permisos_a_un_rol(): void
    {
        $rol = Role::where('name', 'Supervisor')->first();
        Permission::create(['name' => 'reportes.imprimir', 'guard_name' => 'web']);

        $this->actingAs($this->admin())
            ->put(route('roles.permisos.update', $rol->id), [
                'permisos' => ['eventos.consultar', 'reportes.imprimir'],
            ])
            ->assertSessionHasNoErrors();

        $rol = $rol->fresh();
        $this->assertTrue($rol->hasPermissionTo('reportes.imprimir'));
        $this->assertTrue($rol->hasPermissionTo('eventos.consultar'));
        // 'eventos.registrar' venía de la matriz y no fue enviado: se quitó.
        $this->assertFalse($rol->hasPermissionTo('eventos.registrar'));
    }

    public function test_el_admin_de_sistema_conserva_usuarios_gestionar_aunque_se_intente_quitar(): void
    {
        $rol = Role::where('name', 'Administrador de sistema')->first();

        // Se envía la lista sin 'usuarios.gestionar': el service lo re-agrega.
        $this->actingAs($this->admin())
            ->put(route('roles.permisos.update', $rol->id), [
                'permisos' => ['eventos.consultar'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($rol->fresh()->hasPermissionTo('usuarios.gestionar'));
    }

    public function test_un_supervisor_no_puede_gestionar_permisos(): void
    {
        $supervisor = User::factory()->create()->assignRole('Supervisor');

        $this->actingAs($supervisor)
            ->post(route('roles.permisos.store'), ['name' => 'reportes.imprimir'])
            ->assertForbidden();
    }
}
