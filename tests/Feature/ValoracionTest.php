<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValoracionTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;
    private User $inquilino;
    private Propiedad $propiedad;


    protected function setUp(): void
    {
        parent::setUp();

        $this->propietario = User::factory()->create();
        $this->inquilino = User::factory()->create();

        $this->propiedad = Propiedad::create([
            'user_id' => $this->propietario->id,
            'categoria_id' => Categoria::create(['nombre' => 'Casa'])->id,
            'titulo' => 'Casa',
            'descripcion' => 'Casa amplia',
            'precio' => 5000,
            'direccion' => 'Calle 1',
            'ciudad' => 'Puebla',
            'estado' => 'Puebla',
            'estado_publicacion' => 'aprobada',
            'disponible' => true,
        ]);
    }

    public function test_valorar_actualizar_y_ver_promedio(): void
    {
        Sanctum::actingAs($this->inquilino);

        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", [
            'estrellas' => 4,
            'comentario' => 'Muy atento y la casa como en las fotos.',
        ])->assertCreated();

        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->propietario->id, 'tipo' => 'nueva_valoracion']);

        // Volver a valorar actualiza la misma valoración.
        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 5])->assertOk();

        $otro = User::factory()->create();
        Sanctum::actingAs($otro);
        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 2])->assertCreated();

        $this->getJson("/api/usuarios/{$this->propietario->id}/valoraciones")
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('promedio', 3.5)
            ->assertJsonPath('distribucion.5', 1)
            ->assertJsonPath('distribucion.2', 1)
            ->assertJsonPath('mi_valoracion.estrellas', 2)
            ->assertJsonPath('puede_valorar', true);
    }

    public function test_reglas(): void
    {
        Sanctum::actingAs($this->propietario);
        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 5])->assertStatus(422);

        $this->getJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertJsonPath('puede_valorar', false);

        // Cualquier usuario puede calificar a otro, aunque no haya rentado ni publique.
        $this->postJson("/api/usuarios/{$this->inquilino->id}/valoraciones", ['estrellas' => 5])->assertCreated();

        Sanctum::actingAs($this->inquilino);
        $this->getJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertJsonPath('puede_valorar', true);

        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 6])
            ->assertStatus(422)->assertJsonValidationErrors('estrellas');
        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 3, 'comentario' => str_repeat('a', 251)])
            ->assertStatus(422)->assertJsonValidationErrors('comentario');
    }

    public function test_eliminar_mi_valoracion(): void
    {
        Sanctum::actingAs($this->inquilino);

        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 1])->assertCreated();
        $this->deleteJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertOk();
        $this->deleteJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertNotFound();

        $this->getJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertJsonPath('total', 0);
    }

    public function test_sin_sesion_no_se_puede_valorar(): void
    {
        $this->postJson("/api/usuarios/{$this->propietario->id}/valoraciones", ['estrellas' => 5])->assertUnauthorized();
        $this->getJson("/api/usuarios/{$this->propietario->id}/valoraciones")->assertUnauthorized();
    }
}
