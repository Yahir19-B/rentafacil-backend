<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarcarRentadoTest extends TestCase
{
    use RefreshDatabase;

    private User $dueno;
    private User $inquilino;
    private Propiedad $propiedad;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->dueno, $this->inquilino] = User::factory()->count(2)->create();

        $this->propiedad = Propiedad::create([
            'user_id' => $this->dueno->id,
            'categoria_id' => Categoria::create(['nombre' => 'Cuarto'])->id,
            'titulo' => 'Cuarto amueblado',
            'descripcion' => 'Cuarto',
            'precio' => 2500,
            'direccion' => 'Calle 1',
            'ciudad' => 'Toluca',
            'estado' => 'México',
            'estado_publicacion' => 'aprobada',
            'disponible' => true,
        ]);
    }

    /** Pasos 1-2: el inquilino contacta al propietario por mensajes. */
    private function conversar(): void
    {
        $conversacion = Conversacion::create([
            'propiedad_id' => $this->propiedad->id,
            'user_uno_id' => $this->inquilino->id,
            'user_dos_id' => $this->dueno->id,
        ]);

        Mensaje::create([
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $this->inquilino->id,
            'receptor_id' => $this->dueno->id,
            'propiedad_id' => $this->propiedad->id,
            'mensaje' => '¿Sigue disponible?',
            'leido' => false,
        ]);
    }

    private function marcar()
    {
        Sanctum::actingAs($this->dueno);

        return $this->postJson("/api/propiedades/{$this->propiedad->id}/marcar-rentada", [
            'inquilino_id' => $this->inquilino->id,
        ]);
    }

    public function test_proceso_completo_confirmado_habilita_calificar(): void
    {
        $this->conversar();

        Sanctum::actingAs($this->dueno);
        $this->getJson("/api/propiedades/{$this->propiedad->id}/candidatos-renta")
            ->assertOk()
            ->assertJsonPath('0.usuario.id', $this->inquilino->id)
            ->assertJsonPath('0.sobre_esta_publicacion', true);

        $rentaId = $this->marcar()->assertCreated()->json('id');
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->inquilino->id, 'tipo' => 'confirmar_renta']);

        // Mientras sigue pendiente no se puede calificar ni volver a marcar.
        $this->marcar()->assertStatus(422);
        Sanctum::actingAs($this->inquilino);
        $this->postJson("/api/usuarios/{$this->dueno->id}/valoraciones", ['estrellas' => 5])->assertStatus(422);

        $this->getJson('/api/rentas/pendientes')->assertOk()->assertJsonCount(1)->assertJsonPath('0.propiedad.titulo', 'Cuarto amueblado');

        $this->postJson("/api/rentas/{$rentaId}/responder", ['confirmada' => true])
            ->assertOk()
            ->assertJsonPath('puede_calificar', true);

        $propiedad = $this->propiedad->fresh();
        $this->assertNotNull($propiedad->rentada_at);
        $this->assertFalse((bool) $propiedad->disponible);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->dueno->id, 'tipo' => 'renta_confirmada']);

        $this->postJson("/api/usuarios/{$this->dueno->id}/valoraciones", ['estrellas' => 5])->assertCreated();
    }

    public function test_inquilino_dice_que_no_se_concreto(): void
    {
        $this->conversar();
        $rentaId = $this->marcar()->assertCreated()->json('id');

        Sanctum::actingAs($this->inquilino);
        $this->postJson("/api/rentas/{$rentaId}/responder", ['confirmada' => false])->assertOk();

        $this->assertNull($this->propiedad->fresh()->rentada_at);
        $this->assertTrue((bool) $this->propiedad->fresh()->disponible);
        $this->postJson("/api/usuarios/{$this->dueno->id}/valoraciones", ['estrellas' => 5])->assertStatus(422);

        // Ya respondida, no se puede volver a responder.
        $this->postJson("/api/rentas/{$rentaId}/responder", ['confirmada' => true])->assertStatus(422);
    }

    public function test_reglas(): void
    {
        // Sin conversación previa no se puede marcar.
        $this->marcar()->assertStatus(422);

        $this->conversar();

        // Solo el dueño de la publicación.
        Sanctum::actingAs($this->inquilino);
        $this->postJson("/api/propiedades/{$this->propiedad->id}/marcar-rentada", ['inquilino_id' => $this->dueno->id])
            ->assertForbidden();

        // Solo el inquilino responde; el dueño puede cancelar la pendiente.
        $rentaId = $this->marcar()->assertCreated()->json('id');
        $this->postJson("/api/rentas/{$rentaId}/responder", ['confirmada' => true])->assertForbidden();
        $this->postJson("/api/rentas/{$rentaId}/cancelar")->assertOk();
        $this->assertDatabaseMissing('notificaciones', ['user_id' => $this->inquilino->id, 'tipo' => 'confirmar_renta']);
    }

    public function test_volver_a_habilitar_quita_rentado(): void
    {
        $this->propiedad->update(['disponible' => false, 'rentada_at' => now()]);
        $this->propiedad->update(['disponible' => true]);

        $this->assertNull($this->propiedad->fresh()->rentada_at);
    }
}
