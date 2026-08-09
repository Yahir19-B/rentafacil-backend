<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Moderacion;
use App\Models\Propiedad;
use App\Models\User;
use App\Notifications\ImagenSospechosaNotification;
use App\Services\FiltroPalabrasService;
use App\Services\ModeracionImagenService;
use App\Services\NotificacionPropiedadService;
use App\Services\StrikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropiedadController extends Controller
{
    public function __construct(
        private readonly FiltroPalabrasService $filtroPalabras,
        private readonly ModeracionImagenService $moderacionImagen,
        private readonly StrikeService $strikeService,
        private readonly NotificacionPropiedadService $notificacionPropiedad,
    ) {
    }

    /**
     * Registra el strike del usuario, deja constancia en
     * `moderaciones` y arma la respuesta 422 con el mensaje de
     * advertencia/suspensión correspondiente.
     */
    private function respuestaRechazo(
        User $usuario,
        string $motivo,
        string $tipo,
        ?array $etiquetas = null,
        ?int $propiedadId = null,
        string $proveedorIa = 'filtro_palabras',
    ): JsonResponse {
        $resultado = $this->strikeService->registrarStrike($usuario, $motivo);

        Moderacion::create([
            'propiedad_id' => $propiedadId,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'resultado' => 'rechazado',
            'etiquetas_detectadas' => $etiquetas,
            'motivo' => $motivo,
            'proveedor_ia' => $proveedorIa,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => $resultado['mensaje'],
            'strikes' => $resultado['strikes'],
            'suspendido' => $resultado['suspendido'],
        ], 422);
    }

    /**
     * Revisa título, descripción y servicios en busca de lenguaje
     * no permitido. Devuelve las palabras encontradas o un arreglo
     * vacío si el texto es válido.
     */
    private function revisarTextoPropiedad(array $data): array
    {
        $camposTexto = array_filter([
            $data['titulo'] ?? null,
            $data['descripcion'] ?? null,
        ], 'is_string');

        foreach ($data['servicios'] ?? [] as $servicio) {
            if (is_string($servicio)) {
                $camposTexto[] = $servicio;
            }
        }

        foreach ($camposTexto as $texto) {
            $encontradas = $this->filtroPalabras->contieneMalasPalabras($texto);

            if (!empty($encontradas)) {
                return $encontradas;
            }
        }

        return [];
    }

    /**
     * Revisa las imágenes con Google Cloud Vision. Devuelve un
     * arreglo con 'mensaje' y 'etiquetas' o null si todas son válidas.
     */
    private function revisarImagenesPropiedad(array $imagenes): ?array
    {
        $urls = array_column($imagenes, 'url');
        $resultados = $this->moderacionImagen->analizarImagenes($urls);

        $rechazadas = [];
        $etiquetas = [];

        foreach ($resultados as $indice => $resultado) {
            if (!$resultado['aprobada']) {
                $rechazadas[] = $indice + 1;
                $etiquetas[] = $resultado['etiquetas'];
            }
        }

        if (empty($rechazadas)) {
            return null;
        }

        return [
            'mensaje' => 'Detectamos contenido inapropiado en la(s) imagen(es) '
                . implode(', ', $rechazadas)
                . '. Sube fotografías que cumplan con las normas de la comunidad.',
            'etiquetas' => $etiquetas,
        ];
    }

public function index(Request $request)
{
    $query = Propiedad::query()
        ->with([
            'categoria',

            'imagenes' => function ($query) {
                $query
                    ->orderBy('orden', 'asc')
                    ->orderBy('id', 'asc');
            },
        ])
        ->withCount(['vistas as vistas'])
        ->where('estado_publicacion', 'aprobada')
        ->where('disponible', true)
        ->whereHas('user', function ($query) {
            $query->where('status', '!=', 'suspendido');
        });

    /*
    |--------------------------------------------------------------------------
    | Búsqueda general
    |--------------------------------------------------------------------------
    */

   if ($request->filled('buscar')) {
    $buscar = trim($request->input('buscar'));

    $query->where(function ($q) use ($buscar) {
        $q->where('titulo', 'like', "%{$buscar}%")
            ->orWhere('descripcion', 'like', "%{$buscar}%")
            ->orWhere('direccion', 'like', "%{$buscar}%")
            ->orWhere('ciudad', 'like', "%{$buscar}%")
            ->orWhere('estado', 'like', "%{$buscar}%");
    });
}

    /*
    |--------------------------------------------------------------------------
    | Categoría
    |--------------------------------------------------------------------------
    */

    if ($request->filled('categoria_id')) {
        $query->where(
            'categoria_id',
            $request->integer('categoria_id')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ubicación
    |--------------------------------------------------------------------------
    */

    if ($request->filled('ciudad')) {
        $query->where(
            'ciudad',
            'like',
            '%' . trim($request->input('ciudad')) . '%'
        );
    }

    

    /*
    |--------------------------------------------------------------------------
    | Precio
    |--------------------------------------------------------------------------
    */

    if ($request->filled('precio_min')) {
        $query->where(
            'precio',
            '>=',
            $request->input('precio_min')
        );
    }

    if ($request->filled('precio_max')) {
        $query->where(
            'precio',
            '<=',
            $request->input('precio_max')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Restricciones
    |--------------------------------------------------------------------------
    */
    /*
|--------------------------------------------------------------------------
| Restricciones
|--------------------------------------------------------------------------
*/

$restricciones = [
    'solo_estudiantes',
    'acepta_mascotas',
    'acepta_familias',
    'acepta_parejas',
];

foreach ($restricciones as $restriccion) {
    if ($request->boolean($restriccion)) {
        $query->where(
            "restricciones->{$restriccion}",
            true
        );
    }
}

    if ($request->has('solo_estudiantes')) {
        $query->where(
            'restricciones->solo_estudiantes',
            $request->boolean('solo_estudiantes')
        );
    }

    if ($request->has('acepta_mascotas')) {
        $query->where(
            'restricciones->acepta_mascotas',
            $request->boolean('acepta_mascotas')
        );
    }

    if ($request->has('acepta_parejas')) {
        $query->where(
            'restricciones->acepta_parejas',
            $request->boolean('acepta_parejas')
        );
    }

    $propiedades = $query
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->get();

    return response()->json($propiedades);
}

    public function store(Request $request)
{
    $data = $request->validate([
        'categoria_id' => 'required|exists:categorias,id',
        'titulo' => 'required|string|max:255',
        'descripcion' => 'required|string',
        'precio' => 'required|numeric|min:0',
        'deposito' => 'nullable|numeric|min:0',
        'direccion' => 'required|string|max:255',
        'ciudad' => 'required|string|max:100',
        'estado' => 'required|string|max:100',
        'codigo_postal' => 'nullable|string|max:10',
        'latitud' => 'nullable|numeric',
        'longitud' => 'nullable|numeric',
        'restricciones' => 'nullable|array',
        'servicios' => 'nullable|array',
        'disponible' => 'nullable|boolean',

        // Imágenes previamente subidas a Firebase
        'imagenes' => 'required|array|min:1|max:10',
        'imagenes.*.firebase_path' => 'required|string|max:1000',
        'imagenes.*.url' => 'required|string|max:2048',
        'imagenes.*.orden' => 'required|integer|min:0',
    ]);

    $imagenes = $data['imagenes'];

    unset($data['imagenes']);

    $palabrasEncontradas = $this->revisarTextoPropiedad($data);

    if (!empty($palabrasEncontradas)) {
        return $this->respuestaRechazo(
            $request->user(),
            'Lenguaje no permitido en una publicación.',
            'texto',
            $palabrasEncontradas
        );
    }

    $errorImagenes = $this->revisarImagenesPropiedad($imagenes);

    $data['user_id'] = $request->user()->id;
    $data['estado_publicacion'] = $errorImagenes ? 'en_revision' : 'aprobada';
    $data['disponible'] = $data['disponible'] ?? true;

    $imagenEstado = $errorImagenes ? 'en_revision' : 'aprobada';

    $propiedad = DB::transaction(function () use ($data, $imagenes, $errorImagenes, $imagenEstado, $request) {
        $propiedad = Propiedad::create($data);

        $imagenesParaGuardar = array_map(
            function (array $imagen) use ($imagenEstado): array {
                return [
                    'firebase_path' => $imagen['firebase_path'],
                    'url' => $imagen['url'],
                    'orden' => $imagen['orden'],
                    'estado' => $imagenEstado,
                ];
            },
            $imagenes
        );

        $propiedad->imagenes()->createMany($imagenesParaGuardar);

        if ($errorImagenes) {
            Moderacion::create([
                'propiedad_id' => $propiedad->id,
                'user_id' => $request->user()->id,
                'tipo' => 'imagen',
                'resultado' => 'sospechoso',
                'etiquetas_detectadas' => $errorImagenes['etiquetas'],
                'motivo' => 'Posible contenido inapropiado (sangre, +18 o violento) detectado en las imágenes. Pendiente de revisión manual.',
                'proveedor_ia' => 'google_vision',
                'estado' => 'pendiente',
            ]);
        }

        return $propiedad;
    });

    if (!$errorImagenes) {
        $this->notificacionPropiedad->notificarNuevaPublicacion($propiedad);
    } else {
        $this->notificarAdminsImagenSospechosa(
            $propiedad,
            'Posible contenido inapropiado (sangre, +18 o violento) detectado en las imágenes. Pendiente de revisión manual.'
        );
    }

    return response()->json(
        $propiedad->load([
            'user.role',
            'categoria',
            'imagenes',
        ]),
        201
    );
}

private function notificarAdminsImagenSospechosa(Propiedad $propiedad, string $motivo): void
{
    $propiedad->loadMissing('user');

    $admins = User::whereHas('role', fn ($q) => $q->where('nombre', 'admin'))->get();

    foreach ($admins as $admin) {
        $admin->notify(new ImagenSospechosaNotification($propiedad, $motivo));
    }
}

public function show(Propiedad $propiedad)
{
    $propiedad->loadMissing('user');

    $usuarioAutenticado = Auth::guard('sanctum')->user();
    $esAdmin = $usuarioAutenticado?->role?->nombre === 'admin';

    if (
        !$esAdmin && (
            $propiedad->estado_publicacion !== 'aprobada' ||
            !$propiedad->disponible ||
            $propiedad->user->status === 'suspendido'
        )
    ) {
        return response()->json([
            'message' => 'La propiedad no está disponible.'
        ], 404);
    }

    return response()->json(
        $propiedad->load([
            'categoria',
            'imagenes',
            'user'
        ])->loadCount(['vistas as vistas'])
    );
}

public function registrarVista(Request $request, Propiedad $propiedad)
{
    $usuario = $request->user();

    if ($usuario->id !== $propiedad->user_id) {
        $propiedad->vistas()->firstOrCreate([
            'user_id' => $usuario->id,
        ]);
    }

    return response()->json([
        'vistas' => $propiedad->vistas()->count(),
    ]);
}

    public function update(Request $request, Propiedad $propiedad)
    {
        $user = $request->user()->load('role');

        if ($user->role->nombre !== 'admin' && $propiedad->user_id !== $user->id) {
            return response()->json(['message' => 'No puedes editar esta propiedad'], 403);
        }

        $data = $request->validate([
            'categoria_id' => 'sometimes|exists:categorias,id',
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'sometimes|string',
            'precio' => 'sometimes|numeric|min:0',
            'deposito' => 'nullable|numeric|min:0',
            'direccion' => 'sometimes|string|max:255',
            'ciudad' => 'sometimes|string|max:100',
            'estado' => 'sometimes|string|max:100',
            'codigo_postal' => 'nullable|string|max:10',
            'latitud' => 'nullable|numeric',
            'longitud' => 'nullable|numeric',
            'restricciones' => 'nullable|array',
            'servicios' => 'nullable|array',
            'estado_publicacion' => 'sometimes|in:en_revision,aprobada,rechazada',
            'motivo_rechazo' => 'nullable|string',
            'disponible' => 'boolean',
        ]);

        $reencolarModeracionImagen = false;

        if ($user->role->nombre !== 'admin') {
            unset($data['estado_publicacion'], $data['motivo_rechazo']);

            // El dueño edita una publicación rechazada: se reenvía a revisión
            // en vez de quedarse rechazada para siempre.
            if ($propiedad->estado_publicacion === 'rechazada') {
                $data['estado_publicacion'] = 'en_revision';
                $data['motivo_rechazo'] = null;

                // Si la rechazaron por imágenes sospechosas, hay que volver a
                // meterla en la cola de "Revisión de contenido" del admin;
                // de lo contrario ese apartado nunca se entera del reenvío.
                $reencolarModeracionImagen = Moderacion::where('propiedad_id', $propiedad->id)
                    ->where('tipo', 'imagen')
                    ->exists();
            }
        }

        $estadoResultante = $data['estado_publicacion'] ?? $propiedad->estado_publicacion;

        if (($data['disponible'] ?? null) === true && $estadoResultante !== 'aprobada') {
            return response()->json([
                'message' => 'Solo puedes habilitar una publicación que ya esté aprobada.'
            ], 422);
        }

        $palabrasEncontradas = $this->revisarTextoPropiedad($data);

        if (!empty($palabrasEncontradas)) {
            return $this->respuestaRechazo(
                $user,
                'Lenguaje no permitido al editar una publicación.',
                'texto',
                $palabrasEncontradas,
                $propiedad->id
            );
        }

        $estabaAprobada = $propiedad->estado_publicacion === 'aprobada';

        $propiedad->update($data);

        if (!$estabaAprobada && $propiedad->estado_publicacion === 'aprobada') {
            $this->notificacionPropiedad->notificarNuevaPublicacion($propiedad);
        }

        if ($reencolarModeracionImagen) {
            $motivoReenvio = 'El dueño editó y reenvió la publicación tras un rechazo por imágenes sospechosas. Pendiente de revisión manual.';

            Moderacion::create([
                'propiedad_id' => $propiedad->id,
                'user_id' => $propiedad->user_id,
                'tipo' => 'imagen',
                'resultado' => 'sospechoso',
                'motivo' => $motivoReenvio,
                'proveedor_ia' => 'google_vision',
                'estado' => 'pendiente',
            ]);

            $this->notificarAdminsImagenSospechosa($propiedad, $motivoReenvio);
        }

        return response()->json($propiedad->load(['user.role', 'categoria', 'imagenes']));
    }

    public function destroy(Request $request, Propiedad $propiedad)
    {
        $user = $request->user()->load('role');

        if ($user->role->nombre !== 'admin' && $propiedad->user_id !== $user->id) {
            return response()->json(['message' => 'No puedes eliminar esta propiedad'], 403);
        }

        $propiedad->delete();

        return response()->json(['message' => 'Propiedad eliminada']);
    }

   public function misPropiedades(Request $request)
        {
            $usuario = $request->user();

            if (!$usuario) {
                return response()->json([
                    'message' => 'Usuario no autenticado.'
                ], 401);
            }

            $propiedades = Propiedad::query()
                ->with([
                    'categoria',
                    'imagenes',
                ])
                ->withCount(['vistas as vistas'])
                ->where('user_id', $usuario->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            return response()->json($propiedades);
        }

        public function miPropiedad(
    Request $request,
    Propiedad $propiedad
) {
    $usuario = $request->user()->load('role');

    $esAdmin =
        $usuario->role?->nombre === 'admin';

    $esPropietario =
        (int) $propiedad->user_id ===
        (int) $usuario->id;

    if (!$esAdmin && !$esPropietario) {
        return response()->json([
            'message' => 'No puedes editar esta propiedad.'
        ], 403);
    }

    return response()->json(
        $propiedad->load([
            'categoria',
            'imagenes'
        ])
    );
}

}
