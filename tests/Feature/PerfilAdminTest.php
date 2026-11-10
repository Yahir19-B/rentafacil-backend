<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\VerificarCorreoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerfilAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->create([
            'role_id' => Role::create(['nombre' => 'admin'])->id,
            'email' => 'admin@example.com',
        ]);
    }

    public function test_admin_edita_telefono_genero_y_fecha_de_nacimiento(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/profile', [
            'name' => 'Admin Nuevo',
            'email' => 'admin@example.com',
            'phone' => '7221234567',
            'sexo' => 'femenino',
            'fecha_nacimiento' => '1999-04-12',
        ])->assertOk()->assertJsonPath('message', 'Perfil actualizado correctamente');

        $admin = $this->admin->fresh();
        $this->assertSame('Admin Nuevo', $admin->name);
        $this->assertSame('7221234567', $admin->phone);
        $this->assertSame('femenino', $admin->sexo);
        $this->assertStringStartsWith('1999-04-12', (string) $admin->fecha_nacimiento);
        $this->assertNotNull($admin->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_cambiar_correo_pide_verificarlo_de_nuevo(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/profile', ['email' => 'nuevo@example.com'])->assertOk();

        $admin = $this->admin->fresh();
        $this->assertSame('nuevo@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
        Notification::assertSentTo($admin, VerificarCorreoNotification::class);
    }

    public function test_no_puede_usar_un_correo_ocupado(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/profile', ['email' => 'ocupado@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
