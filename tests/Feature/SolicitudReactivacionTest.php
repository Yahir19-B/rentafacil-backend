<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sancion;
use App\Models\SolicitudReactivacion;
use App\Models\User;
use App\Notifications\ReactivacionResueltaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitudReactivacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $baneado;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->create(['role_id' => Role::create(['nombre' => 'admin'])->id]);
        $this->baneado = User::factory()->create([
            'email' => 'baneado@example.com',
            'password' => Hash::make('Secreta#1'),
            'status' => 'baneado',
            'strikes' => 3,
        ]);

        Sancion::create([
            'user_id' => $this->baneado->id,
            'admin_id' => $this->admin->id,
            'tipo' => 'baneo',
            'motivo' => 'Spam',
            'fecha_inicio' => now(),
            'activa' => true,
        ]);
    }

    private function solicitar(string $password = 'Secreta#1')
    {
        return $this->postJson('/api/solicitud-reactivacion', [
            'email' => 'baneado@example.com',
            'password' => $password,
            'mensaje' => 'Fue un malentendido, ya borré las publicaciones repetidas.',
        ]);
    }

    public function test_usuario_baneado_solicita_y_admin_aprueba(): void
    {
        $this->solicitar()->assertCreated();
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->admin->id, 'tipo' => 'solicitud_reactivacion']);

        // Solo una pendiente a la vez.
        $this->solicitar()->assertStatus(422);

        Sanctum::actingAs($this->admin);

        $solicitud = $this->getJson('/api/admin/solicitudes-reactivacion')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.sancion.tipo', 'baneo')
            ->json('0');

        $this->postJson("/api/admin/solicitudes-reactivacion/{$solicitud['id']}/aprobar", ['respuesta' => 'Bienvenido de vuelta'])
            ->assertOk()
            ->assertJsonPath('estado', 'aprobada');

        $usuario = $this->baneado->fresh();
        $this->assertSame('activo', $usuario->status);
        $this->assertSame(0, $usuario->strikes);
        $this->assertFalse((bool) Sancion::first()->activa);
        Notification::assertSentTo($usuario, ReactivacionResueltaNotification::class);

        $this->getJson('/api/admin/solicitudes-reactivacion')->assertJsonCount(0);
        $this->getJson('/api/admin/solicitudes-reactivacion?estado=resueltas')->assertJsonCount(1);
    }

    public function test_rechazar_requiere_respuesta_y_no_reactiva(): void
    {
        $this->solicitar()->assertCreated();
        $solicitud = SolicitudReactivacion::first();

        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/solicitudes-reactivacion/{$solicitud->id}/rechazar")
            ->assertStatus(422)->assertJsonValidationErrors('respuesta');

        $this->postJson("/api/admin/solicitudes-reactivacion/{$solicitud->id}/rechazar", ['respuesta' => 'Reincidiste varias veces.'])
            ->assertOk();

        $this->assertSame('baneado', $this->baneado->fresh()->status);
    }

    public function test_requiere_credenciales_y_cuenta_bloqueada(): void
    {
        $this->solicitar('incorrecta')->assertStatus(401);

        $this->baneado->update(['status' => 'activo']);
        $this->solicitar()->assertStatus(422);
    }
}
