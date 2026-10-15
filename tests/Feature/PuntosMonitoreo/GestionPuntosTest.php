<?php

namespace Tests\Feature\PuntosMonitoreo;

use App\Models\AuditoriaAcceso;
use App\Models\Evento;
use App\Models\PuntoMonitoreo;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * US-024: gestión y carga masiva de Puntos de Monitoreo.
 */
class GestionPuntosTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'codigo' => 'PM-M-100',
            'nombre' => 'Bv. España y Mendoza',
            'jurisdiccion' => 'municipal',
            'zona' => 'Centro',
            'latitud' => -32.4075,
            'longitud' => -63.2403,
            ...$cambios,
        ];
    }

    private function csv(string $contenido, string $nombre = 'pms.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    // --- ABM ------------------------------------------------------------------

    public function test_el_listado_muestra_el_resumen_y_los_pms(): void
    {
        PuntoMonitoreo::factory()->create(['jurisdiccion' => 'municipal']);
        PuntoMonitoreo::factory()->create(['jurisdiccion' => 'provincial', 'latitud' => null, 'longitud' => null]);

        $this->actingAs($this->admin)
            ->get(route('puntos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PuntosMonitoreo/Index')
                ->where('resumen.total', 2)
                ->where('resumen.municipales', 1)
                ->where('resumen.sin_coordenadas', 1)
                ->has('puntos.data', 2)
                ->has('mapa', 1));
    }

    public function test_crea_un_pm_y_lo_audita(): void
    {
        $this->actingAs($this->admin)
            ->post(route('puntos.store'), $this->datos())
            ->assertRedirect(route('puntos.index'));

        $this->assertDatabaseHas('puntos_monitoreo', ['codigo' => 'PM-M-100', 'zona' => 'Centro', 'jurisdiccion' => 'municipal']);
        $this->assertDatabaseHas('auditoria_accesos', ['evento' => AuditoriaAcceso::EVENTO_PM_ALTA, 'user_id' => $this->admin->id]);
    }

    public function test_rechaza_coordenadas_fuera_de_villa_maria_y_codigos_repetidos(): void
    {
        PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-100']);

        $this->actingAs($this->admin)
            ->post(route('puntos.store'), $this->datos(['latitud' => -31.4201, 'longitud' => -64.1888])) // Córdoba capital
            ->assertSessionHasErrors(['codigo', 'latitud', 'longitud']);
    }

    public function test_las_coordenadas_van_juntas_o_ninguna(): void
    {
        $this->actingAs($this->admin)
            ->post(route('puntos.store'), $this->datos(['longitud' => null]))
            ->assertSessionHasErrors('longitud');

        $this->actingAs($this->admin)
            ->post(route('puntos.store'), $this->datos(['latitud' => null, 'longitud' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_actualiza_las_coordenadas_de_un_pm(): void
    {
        $punto = PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-100']);

        $this->actingAs($this->admin)
            ->put(route('puntos.update', $punto), $this->datos(['latitud' => -32.4100, 'longitud' => -63.2500]))
            ->assertRedirect(route('puntos.index'));

        $this->assertEqualsWithDelta(-32.41, $punto->fresh()->latitud, 0.00001);
        $this->assertDatabaseHas('auditoria_accesos', ['evento' => AuditoriaAcceso::EVENTO_PM_MOD]);
    }

    public function test_no_elimina_un_pm_con_eventos(): void
    {
        $conEventos = PuntoMonitoreo::factory()->create();
        Evento::factory()->create(['punto_monitoreo_id' => $conEventos->id]);
        $libre = PuntoMonitoreo::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('puntos.destroy', $conEventos))
            ->assertSessionHas('error');
        $this->assertModelExists($conEventos);

        $this->actingAs($this->admin)
            ->delete(route('puntos.destroy', $libre))
            ->assertSessionHas('success');
        $this->assertModelMissing($libre);
    }

    public function test_solo_el_administrador_de_sistema_gestiona_pms(): void
    {
        foreach (['Supervisor', 'Administrativo'] as $rol) {
            $this->actingAs(User::factory()->create()->assignRole($rol))
                ->get(route('puntos.index'))
                ->assertForbidden();
        }
    }

    // --- Importación masiva ---------------------------------------------------

    public function test_importa_un_csv_creando_y_actualizando_por_codigo(): void
    {
        PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-001', 'nombre' => 'Viejo']);

        $archivo = $this->csv(
            "\u{FEFF}codigo,nombre,jurisdiccion,zona,latitud,longitud\n"
            ."PM-M-001,Plaza Centenario,Municipal,Centro,-32.4075,-63.2403\n"
            ."PM-P-001,Ruta 9 y Av. Perón,provincial,,-32.3900,-63.2700\n"
            ."PM-M-002,Pasaje Ñandubay,municipal,Norte,,\n"
        );

        $this->actingAs($this->admin)
            ->post(route('puntos.importar'), ['archivo' => $archivo])
            ->assertSessionHas('success', 'Importación completa: 2 PM nuevos y 1 actualizados.');

        $this->assertDatabaseCount('puntos_monitoreo', 3);
        $this->assertDatabaseHas('puntos_monitoreo', ['codigo' => 'PM-M-001', 'nombre' => 'Plaza Centenario', 'jurisdiccion' => 'municipal']);
        $this->assertDatabaseHas('puntos_monitoreo', ['codigo' => 'PM-M-002', 'nombre' => 'Pasaje Ñandubay', 'latitud' => null]);
        $this->assertDatabaseHas('auditoria_accesos', ['evento' => AuditoriaAcceso::EVENTO_PM_IMPORT]);
    }

    public function test_acepta_punto_y_coma_coma_decimal_y_encabezados_alternativos(): void
    {
        $archivo = $this->csv(
            "ID;Ubicación;Tipo;Cuadrante;Lat;Lng\n"
            ."PM-M-010;Bv. Sarmiento y Catamarca;MUNICIPAL;Sur;-32,4150;-63,2450\n"
        );

        $this->actingAs($this->admin)
            ->post(route('puntos.importar'), ['archivo' => $archivo])
            ->assertSessionHas('success');

        $punto = PuntoMonitoreo::where('codigo', 'PM-M-010')->sole();
        $this->assertSame('Sur', $punto->zona);
        $this->assertEqualsWithDelta(-32.415, $punto->latitud, 0.00001);
    }

    public function test_con_una_fila_invalida_no_importa_nada_e_informa_cada_error(): void
    {
        $archivo = $this->csv(
            "codigo,nombre,jurisdiccion,latitud,longitud\n"
            ."PM-M-001,Plaza,municipal,-32.4075,-63.2403\n"
            ."PM-M-002,,federal,-31.40,-64.18\n"
            ."PM-M-001,Duplicado,municipal,,\n"
        );

        $this->actingAs($this->admin)
            ->post(route('puntos.importar'), ['archivo' => $archivo])
            ->assertSessionHas('error')
            ->assertSessionHas('erroresImportacion', fn (array $errores) => collect($errores)->contains(fn ($e) => str_starts_with($e, 'Fila 3:'))
                && collect($errores)->contains(fn ($e) => str_contains($e, 'fuera de Villa María'))
                && collect($errores)->contains(fn ($e) => str_contains($e, 'repetido')));

        $this->assertDatabaseCount('puntos_monitoreo', 0);
    }

    public function test_rechaza_un_archivo_sin_las_columnas_obligatorias(): void
    {
        $this->actingAs($this->admin)
            ->post(route('puntos.importar'), ['archivo' => $this->csv("calle,altura\nSan Martín,100\n")])
            ->assertSessionHas('erroresImportacion', ['El archivo no tiene las columnas obligatorias: codigo, nombre y jurisdiccion.']);
    }

    public function test_importa_un_excel(): void
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray([
            ['codigo', 'nombre', 'jurisdiccion', 'latitud', 'longitud'],
            ['PM-P-050', 'Ruta 158 km 2', 'provincial', -32.38, -63.29],
        ]);
        $ruta = tempnam(sys_get_temp_dir(), 'pm').'.xlsx';
        (new Xlsx($libro))->save($ruta);

        $this->actingAs($this->admin)
            ->post(route('puntos.importar'), ['archivo' => new UploadedFile($ruta, 'pms.xlsx', null, null, true)])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('puntos_monitoreo', ['codigo' => 'PM-P-050', 'jurisdiccion' => 'provincial']);
        @unlink($ruta);
    }

    public function test_descarga_la_plantilla(): void
    {
        $respuesta = $this->actingAs($this->admin)->get(route('puntos.plantilla'))->assertOk();

        $this->assertStringContainsString('codigo,nombre,jurisdiccion,zona,latitud,longitud', $respuesta->streamedContent());
    }
}
