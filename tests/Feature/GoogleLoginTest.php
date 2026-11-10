<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'dueno', 'inquilino'] as $nombre) {
            Role::create(['nombre' => $nombre]);
        }
    }

    private function loginConGoogle(string $id, string $email)
    {
        $googleUser = (new GoogleUser())->map([
            'id' => $id,
            'name' => "Usuario {$id}",
            'email' => $email,
            'avatar' => null,
        ]);

        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        $factory = Mockery::mock(\Laravel\Socialite\Contracts\Factory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn($provider);
        Socialite::swap($factory);

        return $this->get('/api/auth/google/callback?state=web');
    }

    public function test_varios_usuarios_pueden_entrar_con_google(): void
    {
        $primero = $this->loginConGoogle('g-1', 'uno@example.com');
        $segundo = $this->loginConGoogle('g-2', 'dos@example.com');

        $this->assertStringContainsString('/auth/callback?token=', $primero->headers->get('Location'));
        $this->assertStringContainsString('/auth/callback?token=', $segundo->headers->get('Location'));
        $this->assertSame(2, User::where('auth_provider', 'google')->count());
    }

    public function test_cuenta_existente_con_correo_se_vincula_a_google(): void
    {
        $this->loginConGoogle('g-1', 'uno@example.com');

        $existente = User::factory()->create(['email' => 'dueno@example.com']);

        $respuesta = $this->loginConGoogle('g-9', 'dueno@example.com');

        $this->assertStringContainsString('/auth/callback?token=', $respuesta->headers->get('Location'));
        $this->assertSame('g-9', $existente->fresh()->google_id);
        $this->assertSame('google', $existente->fresh()->auth_provider);
    }
}
