<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\Role;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReporteVeredictoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dueno;
    private User $inquilino;
    private Propiedad $propiedad;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $roles = collect(['admin', 'dueno', 'inquilino'])
            ->mapWithKeys(fn ($nombre) => [$nombre => Role::create(['nombre' => $nombre])]);

        $this->admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $this->dueno = User::factory()->create(['role_id' => $roles['dueno']->id]);
        $this->inquilino = User::factory()->create(['role_id' => $roles['inquilino']->id]);

        $this->propiedad = Propiedad::create([
            'user_id' => $this->dueno->id,
            'categoria_id' => Categoria::create(['nombre' => 'Casa'])->id,
            'titulo' => 'Casa en el centro',
            'descripcion' => 'Casa amplia',
            'precio' => 5000,
            'direccion' => 'Calle 1',
            'ciudad' => 'Puebla',
            'estado' => 'Puebla',
            'estado_publicacion' => 'aprobada',
            'disponible' => true,
        ]);

        Reporte::create([
            'user_id' => $this->inquilino->id,
            'propiedad_id' => $this->propiedad->id,
            'motivo' => 'Publicación falsa',
            'descripcion' => str_repeat('Las fotos no corresponden al lugar. ', 3),
            'evidencias' => [],
            'estado' => 'pendiente',
        ]);
    }

    private function veredicto(string $veredicto)
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson("/api/admin/reportes/publicaciones/{$this->propiedad->id}/veredicto", [
            'veredicto' => $veredicto,
            'comentario' => 'Revisado con la evidencia.',
        ]);
    }

    public function test_lista_y_detalle_de_publicaciones_reportadas(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/reportes/publicaciones')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->propiedad->id)
            ->assertJsonPath('0.reportes_pendientes_count', 1)
            ->assertJsonPath('0.motivos.Publicación falsa', 1);

        $this->getJson("/api/admin/reportes/publicaciones/{$this->propiedad->id}")
            ->assertOk()
            ->assertJsonPath('reportes.0.user.name', $this->inquilino->name)
            ->assertJsonPath('reportes.0.motivo', 'Publicación falsa');

        $this->getJson('/api/admin/reportes/publicaciones?estado=resuelto')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_solo_admin_puede_dar_veredicto(): void
    {
        Sanctum::actingAs($this->inquilino);

        $this->postJson("/api/admin/reportes/publicaciones/{$this->propiedad->id}/veredicto", ['veredicto' => 'baneado'])
            ->assertForbidden();
    }

    public function test_exonerado_mantiene_la_publicacion(): void
    {
        $this->veredicto('exonerado')->assertOk()->assertJsonPath('reportes_resueltos', 1);

        $this->assertSame('aprobada', $this->propiedad->fresh()->estado_publicacion);
        $this->assertSame('activo', $this->dueno->fresh()->status);
        $this->assertDatabaseHas('reportes', ['estado' => 'descartado', 'veredicto' => 'exonerado', 'admin_id' => $this->admin->id]);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->inquilino->id, 'tipo' => 'reporte_resuelto']);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->dueno->id, 'tipo' => 'reporte_veredicto']);

        // Ya no aparece en pendientes, sí en resueltos.
        $this->getJson('/api/admin/reportes/publicaciones')->assertJsonCount(0);
        $this->getJson('/api/admin/reportes/publicaciones?estado=resuelto')
            ->assertJsonCount(1)
            ->assertJsonPath('0.ultimo_veredicto', 'exonerado');
    }

    public function test_advertido_da_de_baja_y_cuenta_un_strike(): void
    {
        $this->veredicto('advertido')->assertOk()->assertJsonPath('strikes', 1);

        $this->assertSame('rechazada', $this->propiedad->fresh()->estado_publicacion);
        $this->assertSame(1, $this->dueno->fresh()->strikes);
        $this->assertSame('activo', $this->dueno->fresh()->status);
        $this->assertDatabaseHas('reportes', ['estado' => 'revisado', 'veredicto' => 'advertido']);
    }

    public function test_suspendido_da_de_baja_y_suspende_3_dias(): void
    {
        $this->veredicto('suspendido')->assertOk();

        $this->assertSame('rechazada', $this->propiedad->fresh()->estado_publicacion);
        $this->assertSame('suspendido', $this->dueno->fresh()->status);

        $sancion = Sancion::where('user_id', $this->dueno->id)->first();
        $this->assertSame('suspension', $sancion->tipo);
        $this->assertTrue($sancion->fecha_fin->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
    }

    public function test_baneado_quita_la_publicacion_y_desactiva_la_cuenta(): void
    {
        $this->veredicto('baneado')->assertOk();

        $this->assertSame('rechazada', $this->propiedad->fresh()->estado_publicacion);
        $this->assertFalse((bool) $this->propiedad->fresh()->disponible);
        $this->assertSame('baneado', $this->dueno->fresh()->status);
        $this->assertDatabaseHas('sanciones', ['user_id' => $this->dueno->id, 'tipo' => 'baneo']);

        // El historial del reporte se conserva.
        $this->assertDatabaseHas('reportes', ['propiedad_id' => $this->propiedad->id, 'veredicto' => 'baneado']);
    }

    public function test_no_se_puede_dar_veredicto_dos_veces(): void
    {
        $this->veredicto('exonerado')->assertOk();
        $this->veredicto('baneado')->assertStatus(422);

        $this->assertSame('activo', $this->dueno->fresh()->status);
    }

    public function test_veredicto_invalido(): void
    {
        $this->veredicto('perdonado')->assertStatus(422)->assertJsonValidationErrors('veredicto');
    }

    public function test_el_reportante_ve_el_veredicto_en_sus_reportes(): void
    {
        $this->veredicto('advertido')->assertOk();

        Sanctum::actingAs($this->inquilino);

        $this->getJson("/api/reportes?propiedad_id={$this->propiedad->id}")
            ->assertOk()
            ->assertJsonPath('0.veredicto', 'advertido');

        $this->assertSame(1, Notificacion::where('user_id', $this->inquilino->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Reportes de usuarios
    |--------------------------------------------------------------------------
    */

    private function reportarUsuario(User $reportado)
    {
        Sanctum::actingAs($this->inquilino);

        return $this->postJson('/api/reportes', [
            'reportado_id' => $reportado->id,
            'motivo' => 'Lenguaje ofensivo',
            'descripcion' => str_repeat('Me insultó por mensaje varias veces. ', 3),
        ]);
    }

    public function test_inquilino_reporta_a_un_usuario_y_lo_ve_en_mis_reportes(): void
    {
        $this->reportarUsuario($this->dueno)->assertCreated()->assertJsonPath('reportado.id', $this->dueno->id);

        // No se puede repetir mientras siga pendiente, ni reportarse a sí mismo.
        $this->reportarUsuario($this->dueno)->assertStatus(422);
        $this->reportarUsuario($this->inquilino)->assertStatus(422);

        $this->getJson('/api/reportes')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['reportado_id' => $this->dueno->id]);
    }

    public function test_no_se_puede_reportar_publicacion_y_usuario_a_la_vez(): void
    {
        Sanctum::actingAs($this->inquilino);

        $this->postJson('/api/reportes', [
            'propiedad_id' => $this->propiedad->id,
            'reportado_id' => $this->dueno->id,
            'motivo' => 'Spam',
            'descripcion' => str_repeat('Texto de prueba para el reporte. ', 3),
        ])->assertStatus(422);
    }

    public function test_admin_resuelve_reporte_de_usuario(): void
    {
        $this->reportarUsuario($this->dueno)->assertCreated();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/reportes/usuarios')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->dueno->id)
            ->assertJsonPath('0.reportes_pendientes_count', 1);

        $this->getJson("/api/admin/reportes/usuarios/{$this->dueno->id}")
            ->assertOk()
            ->assertJsonPath('reportes.0.user.name', $this->inquilino->name);

        $this->postJson("/api/admin/reportes/usuarios/{$this->dueno->id}/veredicto", ['veredicto' => 'suspendido'])
            ->assertOk();

        $this->assertSame('suspendido', $this->dueno->fresh()->status);
        // Un reporte de usuario no toca sus publicaciones.
        $this->assertSame('aprobada', $this->propiedad->fresh()->estado_publicacion);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->dueno->id, 'tipo' => 'reporte_veredicto']);

        $this->getJson('/api/admin/reportes/usuarios')->assertJsonCount(0);
        $this->getJson('/api/admin/reportes/usuarios?estado=resuelto')
            ->assertJsonCount(1)
            ->assertJsonPath('0.ultimo_veredicto', 'suspendido');
    }
}
