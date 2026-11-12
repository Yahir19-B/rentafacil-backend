<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificacionPropiedadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreferenciasBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private User $inquilino;
    private User $dueno;
    private Categoria $cuarto;
    private Categoria $casa;

    protected function setUp(): void
    {
        parent::setUp();

        $rolInquilino = Role::create(['nombre' => 'inquilino']);
        $this->inquilino = User::factory()->create(['role_id' => $rolInquilino->id]);
        $this->dueno = User::factory()->create(['role_id' => Role::create(['nombre' => 'dueno'])->id]);
        $this->cuarto = Categoria::create(['nombre' => 'Cuarto']);
        $this->casa = Categoria::create(['nombre' => 'Casa']);
    }

    private function propiedad(Categoria $categoria, float $precio, array $restricciones, string $ciudad = 'Toluca'): Propiedad
    {
        return Propiedad::create([
            'user_id' => $this->dueno->id,
            'categoria_id' => $categoria->id,
            'titulo' => "{$categoria->nombre} en {$ciudad}",
            'descripcion' => 'Descripción',
            'precio' => $precio,
            'direccion' => 'Calle 1',
            'ciudad' => $ciudad,
            'estado' => 'Estado de México',
            'restricciones' => $restricciones,
            'estado_publicacion' => 'aprobada',
            'disponible' => true,
        ]);
    }

    private function guardarPreferencias(): void
    {
        Sanctum::actingAs($this->inquilino);

        $this->putJson('/api/preferencias-busqueda', [
            'categorias' => [$this->cuarto->id],
            'caracteristicas' => ['solo_estudiantes', 'acepta_mascotas'],
            'precio_min' => 1000,
            'precio_max' => 3000,
        ])->assertOk();
    }

    public function test_guardar_y_leer_preferencias(): void
    {
        $this->guardarPreferencias();

        $this->getJson('/api/preferencias-busqueda')
            ->assertOk()
            ->assertJsonPath('categorias', [$this->cuarto->id])
            ->assertJsonPath('caracteristicas', ['solo_estudiantes', 'acepta_mascotas'])
            ->assertJsonPath('precio_max', 3000);

        $this->putJson('/api/preferencias-busqueda', [
            'categorias' => [], 'caracteristicas' => ['volar'], 'precio_min' => 5000, 'precio_max' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors(['caracteristicas.0', 'precio_max']);
    }

    public function test_recomendadas_ordena_por_coincidencia(): void
    {
        $ideal = $this->propiedad($this->cuarto, 2000, ['solo_estudiantes' => true, 'acepta_mascotas' => true]);
        $parcial = $this->propiedad($this->cuarto, 2500, ['solo_estudiantes' => false, 'acepta_mascotas' => true]);
        $this->propiedad($this->casa, 9000, []); // no coincide en nada

        $this->guardarPreferencias();

        $ids = collect($this->getJson('/api/propiedades-recomendadas')->assertOk()->json())->pluck('id')->all();

        $this->assertSame([$ideal->id, $parcial->id], $ids);
    }

    public function test_busquedas_recientes_sin_preferencias(): void
    {
        $metepec = $this->propiedad($this->casa, 9000, [], 'Metepec');
        $this->propiedad($this->casa, 9000, [], 'Toluca');

        Sanctum::actingAs($this->inquilino);

        $this->getJson('/api/propiedades-recomendadas?busquedas[]=metepec')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $metepec->id);

        $this->getJson('/api/propiedades-recomendadas')->assertOk()->assertJsonCount(0);
    }

    public function test_aviso_de_inmueble_nuevo_respeta_preferencias(): void
    {
        $sinPreferencias = User::factory()->create(['role_id' => $this->inquilino->role_id]);
        $this->guardarPreferencias();

        $servicio = app(NotificacionPropiedadService::class);

        $servicio->notificarNuevaPublicacion($this->propiedad($this->casa, 9000, []));
        $this->assertSame(0, Notificacion::where('user_id', $this->inquilino->id)->count());
        $this->assertSame(1, Notificacion::where('user_id', $sinPreferencias->id)->count());

        $servicio->notificarNuevaPublicacion(
            $this->propiedad($this->cuarto, 2000, ['solo_estudiantes' => true, 'acepta_mascotas' => true])
        );
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $this->inquilino->id,
            'titulo' => 'Nuevo inmueble que coincide con tus preferencias',
        ]);
    }

    public function test_preferencias_no_se_exponen_en_el_perfil(): void
    {
        $this->guardarPreferencias();

        $this->assertArrayNotHasKey('preferencias_busqueda', $this->inquilino->fresh()->toArray());
    }
}
