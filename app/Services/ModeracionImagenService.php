<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Analiza imágenes con Google Cloud Vision (SafeSearch + Label
 * Detection) para bloquear contenido para adultos, violento, con
 * sangre/heridas o armas en publicaciones. Se activa/desactiva con MODERACION_IA_ACTIVA y
 * requiere GOOGLE_VISION_API_KEY configurada.
 *
 * Si la API falla (red, cuota, key inválida) se aprueba la imagen
 * por defecto ("fail-open"): un problema con Google no debe
 * impedir que los dueños publiquen.
 */
class ModeracionImagenService
{
    private const NIVELES = ['UNKNOWN', 'VERY_UNLIKELY', 'UNLIKELY', 'POSSIBLE', 'LIKELY', 'VERY_LIKELY'];

    /**
     * Nivel mínimo de SafeSearch a partir del cual se rechaza cada
     * categoría. Una imagen con poca sangre casi nunca llega a LIKELY:
     * en pruebas, fotos con una hemorragia nasal pequeña salían como
     * UNLIKELY/POSSIBLE en "violence", mientras que fotos normales de
     * cuartos, cocinas y baños salen VERY_UNLIKELY. Por eso violencia
     * se marca desde UNLIKELY y "medical" desde POSSIBLE (UNLIKELY sí
     * aparece en fotos normales). adult/racy se quedan en LIKELY para
     * no marcar fotos normales de recámaras.
     *
     * Una imagen marcada en una propiedad no se rechaza en automático:
     * la publicación pasa a revisión manual del administrador.
     */
    private const UMBRAL_POR_CATEGORIA = [
        'violence' => 'UNLIKELY',
        'medical' => 'POSSIBLE',
        'adult' => 'LIKELY',
        'racy' => 'LIKELY',
    ];

    /**
     * Etiquetas de LABEL_DETECTION que delatan sangre, heridas o armas
     * aunque SafeSearch califique la imagen como poco violenta.
     * (No incluye "knife": aparecería en fotos de cocinas equipadas.)
     */
    private const ETIQUETAS_PROHIBIDAS = [
        'blood', 'wound', 'injury', 'gore', 'bleeding', 'scar', 'bruise',
        'corpse', 'dead body', 'crime scene',
        'gun', 'firearm', 'handgun', 'rifle', 'weapon', 'ammunition',
    ];

    private const CONFIANZA_MINIMA_ETIQUETA = 0.6;

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
        $descargas = Http::pool(function ($pool) use ($urls) {
            $peticiones = [];

            foreach ($urls as $indice => $url) {
                $peticiones[] = $pool->as((string) $indice)->timeout(10)->get($url);
            }

            return $peticiones;
        });

        $contenidos = [];

        foreach ($urls as $indice => $url) {
            $descarga = $descargas[(string) $indice] ?? null;

            if ($descarga instanceof Response && !$descarga->failed()) {
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

            // Cada petición lleva como clave el índice de su imagen: las
            // respuestas pueden llegar en otro orden y no deben mezclarse.
            $respuestasPorIndice = Http::pool(function ($pool) use ($contenidos, $endpoint) {
                $peticiones = [];

                foreach ($contenidos as $indice => $contenido) {
                    $peticiones[] = $pool->as((string) $indice)
                        ->timeout(10)
                        ->post($endpoint, $this->cuerpoPeticion($contenido));
                }

                return $peticiones;
            });
        }

        $resultados = [];

        foreach ($urls as $indice => $url) {
            $respuesta = $respuestasPorIndice[(string) $indice] ?? null;

            $resultados[$indice] = isset($contenidos[$indice])
                ? $this->interpretar($respuesta instanceof Response ? $respuesta : null, $url)
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
                'features' => [
                    ['type' => 'SAFE_SEARCH_DETECTION'],
                    ['type' => 'LABEL_DETECTION', 'maxResults' => 25],
                ],
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

            foreach (self::UMBRAL_POR_CATEGORIA as $categoria => $umbral) {
                $nivel = $safeSearch[$categoria] ?? 'UNKNOWN';

                if ($this->nivelAlcanza($nivel, $umbral)) {
                    $etiquetasRechazadas[$categoria] = $nivel;
                }
            }

            $etiquetasDetectadas = $this->etiquetasProhibidas(
                $respuesta->json('responses.0.labelAnnotations') ?? []
            );

            foreach ($etiquetasDetectadas as $etiqueta => $confianza) {
                $etiquetasRechazadas["etiqueta:{$etiqueta}"] = $confianza;
            }

            $detalle = $safeSearch + ['etiquetas_prohibidas' => $etiquetasDetectadas];

            if (!empty($etiquetasRechazadas)) {
                return [
                    'aprobada' => false,
                    'motivo' => 'Contenido inapropiado detectado: ' . implode(', ', array_keys($etiquetasRechazadas)),
                    'etiquetas' => $detalle,
                ];
            }

            return [
                'aprobada' => true,
                'motivo' => null,
                'etiquetas' => $detalle,
            ];
        } catch (\Throwable $e) {
            Log::warning('No se pudo analizar imagen con Google Vision, se aprueba por defecto.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->resultadoAprobado();
        }
    }

    private function nivelAlcanza(string $nivel, string $umbral): bool
    {
        $posicion = array_search($nivel, self::NIVELES, true);

        return $posicion !== false
            && $posicion >= array_search($umbral, self::NIVELES, true);
    }

    /**
     * @param array<int, array{description?: string, score?: float}> $etiquetas
     * @return array<string, float> etiqueta prohibida => confianza
     */
    private function etiquetasProhibidas(array $etiquetas): array
    {
        $encontradas = [];

        foreach ($etiquetas as $etiqueta) {
            $nombre = mb_strtolower(trim($etiqueta['description'] ?? ''));
            $confianza = (float) ($etiqueta['score'] ?? 0);

            if ($confianza >= self::CONFIANZA_MINIMA_ETIQUETA
                && in_array($nombre, self::ETIQUETAS_PROHIBIDAS, true)) {
                $encontradas[$nombre] = round($confianza, 2);
            }
        }

        return $encontradas;
    }

    private function resultadoAprobado(): array
    {
        return ['aprobada' => true, 'motivo' => null, 'etiquetas' => null];
    }
}
