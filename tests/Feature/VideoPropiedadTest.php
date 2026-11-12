<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Propiedad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoPropiedadTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = 'https://firebasestorage.googleapis.com/v0/b/app/o/propiedades%2F1%2Fx%2Fvideo.mp4?alt=media';

    private User $dueno;
    private Propiedad $propiedad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dueno = User::factory()->create(['role_id' => Role::create(['nombre' => 'dueno'])->id]);

        $this->propiedad = Propiedad::create([
            'user_id' => $this->dueno->id,
            'categoria_id' => Categoria::create(['nombre' => 'Casa'])->id,
            'titulo' => 'Casa',
            'descripcion' => 'Casa amplia',
            'precio' => 5000,
            'direccion' => 'Calle 1',
            'ciudad' => 'Toluca',
            'estado' => 'México',
            'estado_publicacion' => 'aprobada',
            'disponible' => true,
        ]);

        Sanctum::actingAs($this->dueno);
    }

    public function test_agregar_y_quitar_video(): void
    {
        $this->putJson("/api/propiedades/{$this->propiedad->id}", [
            'video_url' => self::VIDEO,
            'video_path' => 'propiedades/1/x/video.mp4',
        ])->assertOk();

        $this->assertSame(self::VIDEO, $this->propiedad->fresh()->video_url);

        $this->putJson("/api/propiedades/{$this->propiedad->id}", ['video_url' => null])->assertOk();

        $this->assertNull($this->propiedad->fresh()->video_url);
        $this->assertNull($this->propiedad->fresh()->video_path);
    }

    public function test_solo_acepta_videos_de_firebase(): void
    {
        $this->putJson("/api/propiedades/{$this->propiedad->id}", [
            'video_url' => 'https://sitio-raro.com/video.mp4',
            'video_path' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors('video_url');

        $this->putJson("/api/propiedades/{$this->propiedad->id}", ['video_url' => self::VIDEO])
            ->assertStatus(422)->assertJsonValidationErrors('video_path');
    }
}
