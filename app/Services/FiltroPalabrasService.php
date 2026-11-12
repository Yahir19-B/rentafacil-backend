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

        // Violencia y crimen
        'violencia', 'violento', 'violenta', 'violentos', 'violentas',
        'muerte', 'muertes', 'muerto', 'muerta', 'muertos', 'muertas', 'morir',
        'matar', 'matare', 'matanza', 'masacre',
        'asesino', 'asesina', 'asesinos', 'asesinar', 'asesinato', 'asesinado', 'asesinada',
        'homicidio', 'homicida', 'feminicidio',
        'suicidio', 'suicida', 'suicidarse',
        'cadaver', 'cadaveres', 'cadáver', 'cadáveres',
        'tortura', 'torturar', 'torturado',
        'degollar', 'degollado', 'decapitar', 'decapitado',
        'descuartizar', 'descuartizado', 'apuñalar', 'apuñalado', 'ahorcar', 'ahorcado',
        'golpiza', 'golpear',
        'sangre', 'sangriento', 'sangrienta',
        'secuestro', 'secuestrar', 'secuestrado', 'secuestrada', 'levanton', 'levantón',
        'extorsion', 'extorsión', 'extorsionar',
        'sicario', 'sicarios', 'narco', 'narcos', 'narcotrafico', 'narcotráfico', 'narcotraficante',
        'terrorista', 'terroristas', 'terrorismo',
        'arma', 'armas', 'pistola', 'pistolas', 'rifle', 'rifles', 'metralleta', 'metralletas',
        'cuerno de chivo', 'cuernos de chivo',
        'bala', 'balas', 'balazo', 'balazos', 'balacera', 'tiroteo',
        'disparo', 'disparos', 'disparar',
        'bomba', 'bombas', 'explosivo', 'explosivos',
        'fosa', 'fosas',
        'envenenar', 'envenenado',

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

    /**
     * Expresiones normales que contienen una palabra prohibida pero que
     * en conjunto no son violentas ("Día de Muertos", "bomba de agua",
     * "fosa séptica"...). Se quitan del texto antes de revisarlo, así la
     * palabra suelta sigue prohibida pero la expresión completa no.
     */
    private const EXPRESIONES_PERMITIDAS = [
        // Muerte / muertos (tradiciones)
        'dia de muertos', 'dia de los muertos', 'dias de muertos',
        'noche de muertos', 'temporada de muertos', 'fiesta de muertos',
        'altar de muertos', 'altares de muertos', 'ofrenda de muertos',
        'pan de muerto', 'panes de muerto',

        // Armas (direcciones)
        'plaza de armas', 'plaza de las armas',

        // Bombas (equipamiento de la casa)
        'bomba de agua', 'bombas de agua', 'bomba hidraulica', 'bomba hidroneumatica',
        'bomba presurizadora', 'bomba de calor', 'bomba sumergible', 'bomba de gasolina',

        // Fosas (drenaje)
        'fosa septica', 'fosas septicas',

        // Balas
        'a prueba de balas',

        // Sangre
        'pura sangre', 'banco de sangre',

        // Matar (expresiones coloquiales)
        'matar el tiempo',

        // Disparos (dispositivos)
        'disparo de alarma',
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
        $normalizado = $this->quitarExpresionesPermitidas(
            $this->normalizar($texto)
        );

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

        // En algunos sistemas (macOS) TRANSLIT deja marcas de acento
        // sueltas ("dia" -> "d'ia"); se eliminan.
        $texto = str_replace(["'", '"', '`', '^', '~'], '', $texto);
        $texto = strtr($texto, self::SUSTITUCIONES);

        // Colapsa letras repetidas 3+ veces ("puuuuto" -> "puto"),
        // sin tocar dobles legítimas del español (rr, ll, cc).
        $texto = preg_replace('/(.)\1{2,}/u', '$1', $texto) ?? $texto;

        // Unifica espacios para que "dia  de   muertos" coincida igual.
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return $texto;
    }

    private function quitarExpresionesPermitidas(string $textoNormalizado): string
    {
        foreach (self::EXPRESIONES_PERMITIDAS as $expresion) {
            $patron = '/(?<![a-z])' . preg_quote($this->normalizar($expresion), '/') . '(?![a-z])/u';

            $textoNormalizado = preg_replace($patron, ' ', $textoNormalizado) ?? $textoNormalizado;
        }

        return trim($textoNormalizado);
    }

    private function contienePalabra(string $textoNormalizado, string $palabra): bool
    {
        $patron = '/(?<![a-z])' . preg_quote($this->normalizar($palabra), '/') . '(?![a-z])/u';

        return (bool) preg_match($patron, $textoNormalizado);
    }
}
