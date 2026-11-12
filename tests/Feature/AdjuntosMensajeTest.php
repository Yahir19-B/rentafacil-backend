<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdjuntosMensajeTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;
    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->ana, $this->beto] = User::factory()->count(2)->create();
        Sanctum::actingAs($this->ana);
    }

    private function adjunto(string $tipo, string $mime, int $mb, ?float $duracion = null): array
    {
        return [
            'url' => "https://firebasestorage.googleapis.com/v0/b/app/o/mensajes%2F1%2Fx.{$tipo}?alt=media",
            'path' => "mensajes/1/lote/0-x.{$tipo}",
            'tipo' => $tipo,
            'mime' => $mime,
            'nombre' => "archivo.{$tipo}",
            'tamano' => $mb * 1024 * 1024,
            'duracion' => $duracion,
        ];
    }

    private function enviar(array $datos)
    {
        return $this->postJson('/api/mensajes', ['receptor_id' => $this->beto->id] + $datos);
    }

    public function test_mensaje_solo_con_archivos(): void
    {
        $respuesta = $this->enviar(['adjuntos' => [
            $this->adjunto('imagen', 'image/png', 2),
            $this->adjunto('audio', 'audio/mpeg', 5, 120),
            $this->adjunto('video', 'video/mp4', 40, 290),
        ]])->assertCreated();

        $this->assertNull($respuesta->json('mensaje.mensaje'));
        $this->assertCount(3, $respuesta->json('mensaje.adjuntos'));
        $this->assertSame('audio', $respuesta->json('mensaje.adjuntos.1.tipo'));
    }

    public function test_texto_o_archivo_es_obligatorio(): void
    {
        $this->enviar([])->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->enviar(['mensaje' => 'Hola'])->assertCreated();
    }

    public function test_limites(): void
    {
        // Más de 5 archivos.
        $this->enviar(['adjuntos' => array_fill(0, 6, $this->adjunto('imagen', 'image/png', 1))])
            ->assertStatus(422)->assertJsonValidationErrors('adjuntos');

        // Imagen de más de 10 MB.
        $this->enviar(['adjuntos' => [$this->adjunto('imagen', 'image/jpeg', 11)]])->assertStatus(422);

        // Video de más de 50 MB.
        $this->enviar(['adjuntos' => [$this->adjunto('video', 'video/webm', 51, 60)]])->assertStatus(422);

        // Audio de más de 5 minutos.
        $this->enviar(['adjuntos' => [$this->adjunto('audio', 'audio/mpeg', 3, 301)]])
            ->assertStatus(422)->assertJsonValidationErrors('adjuntos.0.duracion');

        // Formato no permitido (audio WAV) y URL fuera de Firebase.
        $this->enviar(['adjuntos' => [$this->adjunto('audio', 'audio/wav', 3, 60)]])->assertStatus(422);
        $this->enviar(['adjuntos' => [['url' => 'https://otro.com/x.png'] + $this->adjunto('imagen', 'image/png', 1)]])
            ->assertStatus(422)->assertJsonValidationErrors('adjuntos.0.url');
    }

    public function test_nota_de_voz_grabada(): void
    {
        $this->enviar(['adjuntos' => [$this->adjunto('audio', 'audio/webm', 1, 12.5)]])
            ->assertCreated()
            ->assertJsonPath('mensaje.adjuntos.0.mime', 'audio/webm');
    }
}
