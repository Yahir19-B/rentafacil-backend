<?php

namespace App\Services;

use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Preferencias de búsqueda y recomendaciones: puntúa cada publicación
 * según qué tanto coincide con las preferencias guardadas del usuario y
 * con sus búsquedas recientes.
 */
class RecomendacionService
{
    public const CARACTERISTICAS = [
        'solo_estudiantes',
        'acepta_mascotas',
        'acepta_familias',
        'acepta_parejas',
    ];

    /** Preferencias normalizadas (siempre con todas las claves). */
    public function preferencias(User $usuario): array
    {
        $guardadas = $usuario->preferencias_busqueda ?? [];

        return [
            'categorias' => array_values(array_map('intval', $guardadas['categorias'] ?? [])),
            'caracteristicas' => array_values(array_intersect($guardadas['caracteristicas'] ?? [], self::CARACTERISTICAS)),
            'precio_min' => isset($guardadas['precio_min']) ? (float) $guardadas['precio_min'] : null,
            'precio_max' => isset($guardadas['precio_max']) ? (float) $guardadas['precio_max'] : null,
            'ciudad' => $guardadas['ciudad'] ?? null,
        ];
    }

    public function tienePreferencias(array $preferencias): bool
    {
        return $preferencias['categorias']
            || $preferencias['caracteristicas']
            || $preferencias['precio_min'] !== null
            || $preferencias['precio_max'] !== null
            || $preferencias['ciudad'];
    }

    /**
     * @param string[] $busquedas búsquedas recientes del usuario
     * @return Collection<Propiedad> ordenadas por puntaje (solo las que coinciden)
     */
    public function recomendar(User $usuario, array $busquedas = [], int $limite = 10): Collection
    {
        $preferencias = $this->preferencias($usuario);
        $terminos = collect($busquedas)
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->unique()
            ->take(5);

        if (!$this->tienePreferencias($preferencias) && $terminos->isEmpty()) {
            return collect();
        }

        return Propiedad::query()
            ->with(['categoria', 'user', 'imagenes' => fn ($q) => $q->orderBy('orden')->orderBy('id')])
            ->withCount(['vistas as vistas'])
            ->where('estado_publicacion', 'aprobada')
            ->where('disponible', true)
            ->where('user_id', '!=', $usuario->id)
            ->whereHas('user', fn ($q) => $q->whereNotIn('status', ['suspendido', 'baneado']))
            ->latest()
            ->limit(300)
            ->get()
            ->map(function (Propiedad $propiedad) use ($preferencias, $terminos) {
                $propiedad->setAttribute('puntaje_recomendacion', $this->puntaje($propiedad, $preferencias, $terminos));

                return $propiedad;
            })
            ->filter(fn (Propiedad $p) => $p->puntaje_recomendacion > 0)
            ->sortByDesc('puntaje_recomendacion')
            ->take($limite)
            ->values();
    }

    /** ¿La publicación coincide con las preferencias? (para avisar de inmuebles nuevos) */
    public function coincide(Propiedad $propiedad, array $preferencias): bool
    {
        if ($preferencias['categorias'] && !in_array((int) $propiedad->categoria_id, $preferencias['categorias'], true)) {
            return false;
        }

        if (!$this->dentroDePrecio($propiedad, $preferencias)) {
            return false;
        }

        $restricciones = $propiedad->restricciones ?? [];

        foreach ($preferencias['caracteristicas'] as $caracteristica) {
            if (empty($restricciones[$caracteristica])) {
                return false;
            }
        }

        return true;
    }

    private function puntaje(Propiedad $propiedad, array $preferencias, Collection $terminos): int
    {
        $puntaje = 0;

        if ($preferencias['categorias'] && in_array((int) $propiedad->categoria_id, $preferencias['categorias'], true)) {
            $puntaje += 3;
        }

        if (($preferencias['precio_min'] !== null || $preferencias['precio_max'] !== null)) {
            $puntaje += $this->dentroDePrecio($propiedad, $preferencias) ? 3 : -2;
        }

        $restricciones = $propiedad->restricciones ?? [];

        foreach ($preferencias['caracteristicas'] as $caracteristica) {
            $puntaje += !empty($restricciones[$caracteristica]) ? 2 : -1;
        }

        if ($preferencias['ciudad'] && $this->contiene($propiedad, mb_strtolower($preferencias['ciudad']))) {
            $puntaje += 2;
        }

        foreach ($terminos as $termino) {
            if ($this->contiene($propiedad, $termino)) {
                $puntaje += 2;
            }
        }

        return $puntaje;
    }

    private function dentroDePrecio(Propiedad $propiedad, array $preferencias): bool
    {
        $precio = (float) $propiedad->precio;

        return ($preferencias['precio_min'] === null || $precio >= $preferencias['precio_min'])
            && ($preferencias['precio_max'] === null || $precio <= $preferencias['precio_max']);
    }

    private function contiene(Propiedad $propiedad, string $termino): bool
    {
        $texto = mb_strtolower(implode(' ', [
            $propiedad->titulo, $propiedad->colonia, $propiedad->ciudad, $propiedad->estado, $propiedad->direccion,
        ]));

        return str_contains($texto, $termino);
    }
}
