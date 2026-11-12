<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuspensionManualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['nombre' => 'admin'])->id]);
        $this->usuario = User::factory()->create([
            'role_id' => Role::create(['nombre' => 'inquilino'])->id,
            'email' => 'inquilino@example.com',
            'password' => Hash::make('Secreta#1'),
            'status' => 'activo',
        ]);
    }

    public function test_admin_suspende_con_duracion_y_el_login_se_bloquea(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/usuarios/{$this->usuario->id}/suspender", [
            'motivo' => 'Lenguaje ofensivo en comentarios',
            'dias' => 7,
        ])->assertOk()->assertJsonPath('status', 'suspendido');

        $sancion = Sancion::where('user_id', $this->usuario->id)->first();
        $this->assertSame('suspension', $sancion->tipo);
        $this->assertSame($this->admin->id, $sancion->admin_id);
        $this->assertTrue($sancion->fecha_fin->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertDatabaseHas('notificaciones', ['user_id' => $this->usuario->id, 'tipo' => 'cuenta_suspendida']);

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/login', ['email' => 'inquilino@example.com', 'password' => 'Secreta#1'])
            ->assertStatus(403)
            ->assertJsonPath('account_status', 'suspendido');
    }

    public function test_al_vencer_la_suspension_puede_entrar(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/usuarios/{$this->usuario->id}/suspender", ['motivo' => 'Prueba', 'dias' => 1])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->travel(2)->days();

        $this->postJson('/api/login', ['email' => 'inquilino@example.com', 'password' => 'Secreta#1'])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertSame('activo', $this->usuario->fresh()->status);
    }

    public function test_validaciones(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/usuarios/{$this->usuario->id}/suspender", ['motivo' => 'x', 'dias' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('dias');

        $this->postJson("/api/admin/usuarios/{$this->admin->id}/suspender", ['motivo' => 'x', 'dias' => 3])
            ->assertStatus(422);
    }
}
