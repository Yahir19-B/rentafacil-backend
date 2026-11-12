<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\SolicitudAmistad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelarSolicitudAmistadTest extends TestCase
{
    use RefreshDatabase;

    public function test_quien_envio_puede_cancelar_y_volver_a_enviar(): void
    {
        [$ana, $beto] = User::factory()->count(2)->create();

        Sanctum::actingAs($ana);
        $id = $this->postJson("/api/usuarios/{$beto->id}/solicitud-amistad")->assertCreated()->json('id');
        $this->assertSame(1, Notificacion::where('user_id', $beto->id)->count());

        $this->deleteJson("/api/solicitudes-amistad/{$id}")->assertOk();

        $this->assertDatabaseMissing('solicitudes_amistad', ['id' => $id]);
        $this->assertSame(0, Notificacion::where('user_id', $beto->id)->count());
        $this->getJson("/api/usuarios/{$beto->id}/estado-amistad")->assertJsonPath('estado', 'ninguna');

        // Se puede volver a enviar.
        $this->postJson("/api/usuarios/{$beto->id}/solicitud-amistad")->assertCreated();
    }

    public function test_el_destinatario_no_puede_cancelar(): void
    {
        [$ana, $beto] = User::factory()->count(2)->create();

        $solicitud = SolicitudAmistad::create([
            'remitente_id' => $ana->id,
            'destinatario_id' => $beto->id,
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($beto);
        $this->deleteJson("/api/solicitudes-amistad/{$solicitud->id}")->assertForbidden();
    }

    public function test_no_se_cancela_una_solicitud_aceptada(): void
    {
        [$ana, $beto] = User::factory()->count(2)->create();

        $solicitud = SolicitudAmistad::create([
            'remitente_id' => $ana->id,
            'destinatario_id' => $beto->id,
            'estado' => 'aceptada',
        ]);

        Sanctum::actingAs($ana);
        $this->deleteJson("/api/solicitudes-amistad/{$solicitud->id}")->assertStatus(422);
    }

    public function test_lista_de_mis_amigos(): void
    {
        [$ana, $beto, $carla] = User::factory()->count(3)->create();

        SolicitudAmistad::create(['remitente_id' => $ana->id, 'destinatario_id' => $beto->id, 'estado' => 'aceptada']);
        SolicitudAmistad::create(['remitente_id' => $carla->id, 'destinatario_id' => $ana->id, 'estado' => 'pendiente']);

        Sanctum::actingAs($ana);

        $this->getJson('/api/amigos')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $beto->id);
    }
}
