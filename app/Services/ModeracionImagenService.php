<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analiza imágenes con Google Cloud Vision (SafeSearch Detection)
 * para bloquear contenido para adultos, violento o "racy" en
 * publicaciones. Se activa/desactiva con MODERACION_IA_ACTIVA y
 * requiere GOOGLE_VISION_API_KEY configurada.
 *
 * Si la API falla (red, cuota, key inválida) se aprueba la imagen
 * por defecto ("fail-open"): un problema con Google no debe
 * impedir que los dueños publiquen.
 */
class ModeracionImagenService
{
    private const NIVELES_RECHAZO = ['LIKELY', 'VERY_LIKELY'];

    private const CATEGORIAS_SENSIBLES = ['adult', 'violence', 'racy', 'medical'];

    public function activa(): bool
    {
        return (bool) config('services.moderacion.activa')
            && filled(config('services.google_vision.key'));
    }

    /**
     * @param string[] $urls
     * @return array<int, array{aprobada: bool, motivo: ?string, etiquetas: ?array}>
     */
    public function analizarImagenes(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        if (!$this->activa()) {
            return array_map(fn () => $this->resultadoAprobado(), $urls);
        }

        $urls = array_values($urls);

        // Vision no permite leer URLs externas arbitrarias por sí solo
        // (solo Google Cloud Storage/Photos) — hay que descargar la
        // imagen y mandarla como contenido en base64.
        $descargas = Http::pool(
            fn ($pool) => array_map(
                fn (string $url) => $pool->timeout(10)->get($url),
                $urls
            )
        );

        $contenidos = [];

        foreach ($urls as $indice => $url) {
            $descarga = $descargas[$indice] ?? null;

            if ($descarga && !$descarga->failed()) {
                $contenidos[$indice] = base64_encode($descarga->body());
            } else {
                Log::warning('No se pudo descargar la imagen para moderarla, se aprueba por defecto.', [
                    'url' => $url,
                ]);
            }
        }

        $respuestasPorIndice = [];

        if (!empty($contenidos)) {
            $endpoint = 'https://vision.googleapis.com/v1/images:annotate?key='
                . config('services.google_vision.key');

            $indicesValidos = array_keys($contenidos);

            $respuestasVision = Http::pool(
                fn ($pool) => array_map(
                    fn (string $contenido) => $pool->timeout(10)->post($endpoint, $this->cuerpoPeticion($contenido)),
                    array_values($contenidos)
                )
            );

            $respuestasPorIndice = array_combine($indicesValidos, $respuestasVision);
        }

        $resultados = [];

        foreach ($urls as $indice => $url) {
            $resultados[$indice] = isset($respuestasPorIndice[$indice])
                ? $this->interpretar($respuestasPorIndice[$indice], $url)
                : $this->resultadoAprobado();
        }

        return $resultados;
    }

    public function analizarImagen(string $url): array
    {
        return $this->analizarImagenes([$url])[0] ?? $this->resultadoAprobado();
    }

    private function cuerpoPeticion(string $contenidoBase64): array
    {
        return [
            'requests' => [[
                'image' => ['content' => $contenidoBase64],
                'features' => [['type' => 'SAFE_SEARCH_DETECTION']],
            ]],
        ];
    }

    private function interpretar(?Response $respuesta, string $url): array
    {
        try {
            if (!$respuesta || $respuesta->failed()) {
                throw new \RuntimeException(
                    'Vision API no respondió correctamente (' . ($respuesta?->status() ?? 'sin respuesta') . ').'
                );
            }

            $error = $respuesta->json('responses.0.error');

            if ($error) {
                throw new \RuntimeException('Vision API devolvió un error: ' . ($error['message'] ?? 'desconocido'));
            }

            $safeSearch = $respuesta->json('responses.0.safeSearchAnnotation');

            if (!$safeSearch) {
                throw new \RuntimeException('Respuesta de Vision sin safeSearchAnnotation.');
            }

            $etiquetasRechazadas = [];

            foreach (self::CATEGORIAS_SENSIBLES as $categoria) {
                $nivel = $safeSearch[$categoria] ?? 'UNKNOWN';

                if (in_array($nivel, self::NIVELES_RECHAZO, true)) {
                    $etiquetasRechazadas[$categoria] = $nivel;
                }
            }

            if (!empty($etiquetasRechazadas)) {
                return [
                    'aprobada' => false,
                    'motivo' => 'Contenido inapropiado detectado: ' . implode(', ', array_keys($etiquetasRechazadas)),
                    'etiquetas' => $safeSearch,
                ];
            }

            return [
                'aprobada' => true,
                'motivo' => null,
                'etiquetas' => $safeSearch,
            ];
        } catch (\Throwable $e) {
            Log::warning('No se pudo analizar imagen con Google Vision, se aprueba por defecto.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->resultadoAprobado();
        }
    }

    private function resultadoAprobado(): array
    {
        return ['aprobada' => true, 'motivo' => null, 'etiquetas' => null];
    }
}
