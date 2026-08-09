<?php

namespace App\Services;

/**
 * Filtro de lenguaje ofensivo para títulos, descripciones,
 * servicios y comentarios. Normaliza el texto (acentos,
 * mayúsculas, leetspeak y letras repetidas) antes de comparar
 * contra la lista de palabras prohibidas, para dificultar que
 * se evada el filtro con variantes simples (p4to, puuuto, etc).
 */
class FiltroPalabrasService
{
    private const PALABRAS_PROHIBIDAS = [
        // Español
        'puto', 'puta', 'putos', 'putas', 'putazo',
        'pendejo', 'pendeja', 'pendejos', 'pendejas',
        'mierda', 'mierdas',
        'cabron', 'cabrona', 'cabrones',
        'verga', 'vergas',
        'chinga', 'chingada', 'chingado', 'chingar', 'chingas', 'chingón', 'chingon',
        'joder', 'jodido', 'jodida',
        'coño', 'cono',
        'gilipollas',
        'idiota', 'imbecil', 'imbécil',
        'estupido', 'estúpido', 'estupida', 'estúpida',
        'maricon', 'maricón', 'marica',
        'zorra', 'zorras',
        'perra', 'perras',
        'culero', 'culera', 'culeros',
        'pinche', 'pinches',
        'nalga', 'nalgas',
        'panocha', 'pito',
        'follar', 'follando',
        'polla', 'pollas',
        'chocho',
        'cagada', 'cagar', 'cagon', 'cagón',
        'putero',
        'malparido', 'malparida',
        'hijueputa', 'hpta',
        'ojete',
        'naco', 'naca',
        'retrasado', 'retrasada', 'retardado',
        'violador', 'violacion', 'violación',

        // Inglés (frecuentes en México/EU)
        'fuck', 'fucking', 'fucker', 'motherfucker',
        'shit', 'bullshit',
        'bitch', 'bitches',
        'asshole',
        'dick', 'cock',
        'whore', 'slut',
        'cunt',
        'nigger', 'nigga',
        'faggot',
        'retard',
    ];

    private const SUSTITUCIONES = [
        '@' => 'a',
        '4' => 'a',
        '3' => 'e',
        '1' => 'i',
        '!' => 'i',
        '0' => 'o',
        '$' => 's',
        '5' => 's',
        '7' => 't',
    ];

    /**
     * Devuelve las palabras prohibidas encontradas en el texto.
     * Un arreglo vacío significa que el texto es válido.
     */
    public function contieneMalasPalabras(string $texto): array
    {
        $normalizado = $this->normalizar($texto);

        if ($normalizado === '') {
            return [];
        }

        $encontradas = [];

        foreach (self::PALABRAS_PROHIBIDAS as $palabra) {
            if ($this->contienePalabra($normalizado, $palabra)) {
                $encontradas[] = $palabra;
            }
        }

        return $encontradas;
    }

    public function esValido(string $texto): bool
    {
        return empty($this->contieneMalasPalabras($texto));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto;
        $texto = strtr($texto, self::SUSTITUCIONES);

        // Colapsa letras repetidas 3+ veces ("puuuuto" -> "puto"),
        // sin tocar dobles legítimas del español (rr, ll, cc).
        $texto = preg_replace('/(.)\1{2,}/u', '$1', $texto) ?? $texto;

        return $texto;
    }

    private function contienePalabra(string $textoNormalizado, string $palabra): bool
    {
        $patron = '/(?<![a-z])' . preg_quote($palabra, '/') . '(?![a-z])/u';

        return (bool) preg_match($patron, $textoNormalizado);
    }
}
