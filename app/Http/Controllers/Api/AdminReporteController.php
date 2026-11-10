<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\Sancion;
use App\Models\User;
use App\Services\StrikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Caso de uso "Gestionar reportes (Administrador)".
 *
 * Seleccionar reportes (de publicaciones o de usuarios) → Mostrar los
 * reportados con detalles de reportes → Seleccionar uno → Mostrar detalles
 * del reporte → Evaluar → Asignar estatus (exonerado, advertido,
 * suspendido, baneado) → Guardar veredicto → Notificar al reportante y al
 * reportado → Finalizar proceso.
 */
class AdminReporteController extends Controller
{
    public function __construct(
        private readonly StrikeService $strikeService,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Reportes de publicaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Publicaciones que tienen reportes, agrupadas.
     * ?estado=pendiente (default) solo las que tienen reportes sin resolver;
     * ?estado=resuelto las que ya tienen veredicto.
     */
    public function index(Request $request)
    {
        $query = Propiedad::with(['user:id,name,email,foto_perfil,status', 'imagenes'])
            ->withCount([
                'reportes',
                'reportes as reportes_pendientes_count' => fn ($q) => $q->where('estado', 'pendiente'),
            ])
            ->withMax('reportes', 'created_at');

        $this->filtrarPorEstado($query, $request->input('estado', 'pendiente'));

        $propiedades = $query->orderByDesc('reportes_max_created_at')->get();

        $propiedades->each(fn (Propiedad $propiedad) => $this->agregarResumen($propiedad, $propiedad->reportes()));

        return response()->json($propiedades);
    }

    /**
     * Mostrar detalles del reporte: publicación, motivo, descripción,
     * evidencia fotográfica y nombre del reportante de cada reporte.
     */
    public function show(Propiedad $propiedad)
    {
        $propiedad->load([
            'user:id,name,email,foto_perfil,status,strikes',
            'imagenes',
            'categoria',
            'reportes' => fn ($q) => $q->latest(),
            'reportes.user:id,name,email,foto_perfil',
            'reportes.admin:id,name',
        ]);

        return response()->json($propiedad);
    }

    /**
     * Guardar veredicto sobre una publicación:
     * - exonerado:  la publicación puede seguir en la plataforma.
     * - advertido:  se da de baja la publicación y se cuenta un strike.
     * - suspendido: se da de baja la publicación y se suspende la cuenta 3 días.
     * - baneado:    se elimina la publicación y se desactiva la cuenta.
     */
    public function veredicto(Request $request, Propiedad $propiedad)
    {
        [$veredicto, $comentario] = $this->validarVeredicto($request);

        $pendientes = $propiedad->reportes()->where('estado', 'pendiente')->get();

        if ($pendientes->isEmpty()) {
            return response()->json(['message' => 'Esta publicación ya no tiene reportes pendientes.'], 422);
        }

        return $this->resolver(
            $pendientes,
            $propiedad->user,
            $propiedad,
            $veredicto,
            $comentario,
            "la publicación \"{$propiedad->titulo}\""
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reportes de usuarios
    |--------------------------------------------------------------------------
    */

    /** Usuarios que fueron reportados directamente, agrupados. */
    public function usuarios(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'foto_perfil', 'status', 'strikes', 'role_id', 'created_at'])
            ->with('role:id,nombre')
            ->withCount([
                'reportesComoUsuario as reportes_count',
                'reportesComoUsuario as reportes_pendientes_count' => fn ($q) => $q->where('estado', 'pendiente'),
            ])
            ->withMax('reportesComoUsuario as reportes_max_created_at', 'created_at');

        $this->filtrarPorEstado($query, $request->input('estado', 'pendiente'), 'reportesComoUsuario');

        $usuarios = $query->orderByDesc('reportes_max_created_at')->get();

        $usuarios->each(fn (User $usuario) => $this->agregarResumen($usuario, $usuario->reportesComoUsuario()));

        return response()->json($usuarios);
    }

    /** Detalle de un usuario reportado con todos sus reportes. */
    public function showUsuario(User $usuario)
    {
        $usuario->load([
            'role:id,nombre',
            'reportesComoUsuario' => fn ($q) => $q->latest(),
            'reportesComoUsuario.user:id,name,email,foto_perfil',
            'reportesComoUsuario.admin:id,name',
        ]);

        return response()->json(array_merge(
            $usuario->only(['id', 'name', 'email', 'foto_perfil', 'status', 'strikes', 'ciudad', 'biografia', 'created_at']),
            [
                'role' => $usuario->role,
                'propiedades_count' => $usuario->propiedades()->count(),
                'reportes' => $usuario->reportesComoUsuario,
            ]
        ));
    }

    /**
     * Guardar veredicto sobre un usuario:
     * - exonerado:  no se aplica ninguna medida.
     * - advertido:  se cuenta un strike.
     * - suspendido: se suspende la cuenta 3 días.
     * - baneado:    se desactiva la cuenta.
     */
    public function veredictoUsuario(Request $request, User $usuario)
    {
        [$veredicto, $comentario] = $this->validarVeredicto($request);

        $pendientes = $usuario->reportesComoUsuario()->where('estado', 'pendiente')->get();

        if ($pendientes->isEmpty()) {
            return response()->json(['message' => 'Este usuario ya no tiene reportes pendientes.'], 422);
        }

        return $this->resolver($pendientes, $usuario, null, $veredicto, $comentario, 'tu cuenta');
    }

    /*
    |--------------------------------------------------------------------------
    | Flujo común del veredicto
    |--------------------------------------------------------------------------
    */

    /** @return array{0: string, 1: ?string} */
    private function validarVeredicto(Request $request): array
    {
        $data = $request->validate([
            'veredicto' => ['required', Rule::in(Reporte::VEREDICTOS)],
            'comentario' => 'nullable|string|max:1000',
        ], [
            'veredicto.in' => 'Selecciona un estatus válido para el reporte.',
        ]);

        return [$data['veredicto'], trim($data['comentario'] ?? '') ?: null];
    }

    /**
     * Aplica las medidas, resuelve los reportes pendientes y notifica al
     * reportante y al reportado. $propiedad es null en reportes de usuario.
     */
    private function resolver(
        Collection $pendientes,
        ?User $reportado,
        ?Propiedad $propiedad,
        string $veredicto,
        ?string $comentario,
        string $objetivo
    ) {
        if ($veredicto !== 'exonerado' && $reportado?->role?->nombre === 'admin') {
            return response()->json(['message' => 'No puedes sancionar a un administrador.'], 422);
        }

        $motivos = $pendientes->pluck('motivo')->unique()->implode(', ');
        $motivoSancion = 'Reporte de ' . ($propiedad ? $objetivo : "la cuenta de {$reportado?->name}")
            . ": {$motivos}." . ($comentario ? " {$comentario}" : '');

        $extra = DB::transaction(function () use ($pendientes, $reportado, $propiedad, $veredicto, $comentario, $motivoSancion, $objetivo) {
            $extra = [];

            if ($propiedad && $veredicto !== 'exonerado') {
                $this->darDeBaja(
                    $propiedad,
                    ($veredicto === 'baneado' ? 'Publicación eliminada por baneo. ' : '') . $motivoSancion
                );
            }

            if ($reportado) {
                match ($veredicto) {
                    'exonerado' => null,
                    'advertido' => $extra = $this->advertir($reportado, $motivoSancion),
                    'suspendido' => $this->suspender($reportado, $motivoSancion),
                    'baneado' => $this->banear($reportado, $motivoSancion),
                };
            }

            Reporte::whereIn('id', $pendientes->pluck('id'))->update([
                'estado' => $veredicto === 'exonerado' ? 'descartado' : 'revisado',
                'veredicto' => $veredicto,
                'admin_id' => Auth::id(),
                'comentario_admin' => $comentario,
                'resuelto_at' => now(),
            ]);

            $this->notificarReportantes($pendientes, $propiedad, $reportado, $veredicto);

            if ($reportado) {
                $this->notificarReportado($reportado, $propiedad, $objetivo, $veredicto, $comentario, $extra);
            }

            return $extra;
        });

        return response()->json([
            'message' => 'Veredicto guardado. Se notificó al reportante y al reportado.',
            'veredicto' => $veredicto,
            'reportes_resueltos' => $pendientes->count(),
            'reportantes_notificados' => $pendientes->pluck('user_id')->unique()->count(),
            'strikes' => $extra['strikes'] ?? null,
            'suspendido_por_strikes' => $extra['suspendido'] ?? false,
        ]);
    }

    private function filtrarPorEstado($query, string $estado, string $relacion = 'reportes'): void
    {
        if ($estado === 'resuelto') {
            $query->whereHas($relacion, fn ($q) => $q->whereNotNull('veredicto'))
                ->whereDoesntHave($relacion, fn ($q) => $q->where('estado', 'pendiente'));
        } else {
            $query->whereHas($relacion, fn ($q) => $q->where('estado', 'pendiente'));
        }
    }

    /** Motivos más reportados y último veredicto, para la tarjeta del listado. */
    private function agregarResumen($modelo, $relacion): void
    {
        $reportes = $relacion->latest()->get(['motivo', 'veredicto', 'resuelto_at']);

        $modelo->setAttribute('motivos', $reportes->countBy('motivo'));
        $modelo->setAttribute('ultimo_veredicto', $reportes->firstWhere('veredicto', '!=', null)?->veredicto);
    }

    /*
    |--------------------------------------------------------------------------
    | Medidas
    |--------------------------------------------------------------------------
    */

    /**
     * La publicación se quita de la plataforma. En el baneo se marca como
     * rechazada en lugar de borrar la fila para no perder el historial de
     * reportes (reportes.propiedad_id se borra en cascada).
     */
    private function darDeBaja(Propiedad $propiedad, string $motivo): void
    {
        $propiedad->update([
            'estado_publicacion' => 'rechazada',
            'motivo_rechazo' => $motivo,
            'disponible' => false,
        ]);
    }

    /** @return array{strikes: int, suspendido: bool} */
    private function advertir(User $usuario, string $motivo): array
    {
        // Al llegar al límite de strikes, StrikeService suspende la cuenta solo.
        $strike = $this->strikeService->registrarStrike($usuario, $motivo, Auth::id());

        return ['strikes' => $strike['strikes'], 'suspendido' => $strike['suspendido']];
    }

    private function suspender(User $usuario, string $motivo): void
    {
        $usuario->update(['status' => 'suspendido']);
        $usuario->tokens()->delete();

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => Auth::id(),
            'tipo' => 'suspension',
            'motivo' => $motivo,
            'fecha_inicio' => now(),
            // Se reactiva sola al iniciar sesión pasados los días (AuthController).
            'fecha_fin' => now()->addDays(StrikeService::DIAS_SUSPENSION),
            'activa' => true,
        ]);
    }

    private function banear(User $usuario, string $motivo): void
    {
        $usuario->update(['status' => 'baneado']);
        $usuario->tokens()->delete();

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => Auth::id(),
            'tipo' => 'baneo',
            'motivo' => $motivo,
            'fecha_inicio' => now(),
            'fecha_fin' => null,
            'activa' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    */

    /** Notificar al reportante el resultado del reporte. */
    private function notificarReportantes(Collection $pendientes, ?Propiedad $propiedad, ?User $reportado, string $veredicto): void
    {
        $dias = StrikeService::DIAS_SUSPENSION;

        $resultado = $propiedad
            ? match ($veredicto) {
                'exonerado' => 'Revisamos la publicación y no encontramos una infracción al reglamento, por lo que seguirá disponible.',
                'advertido' => 'Confirmamos la infracción: la publicación fue dada de baja y el dueño recibió una advertencia.',
                'suspendido' => "Confirmamos la infracción: la publicación fue dada de baja y la cuenta del dueño fue suspendida por {$dias} días.",
                'baneado' => 'Confirmamos la infracción: la publicación fue eliminada y la cuenta del dueño fue desactivada.',
            }
            : match ($veredicto) {
                'exonerado' => 'Revisamos la cuenta y no encontramos una infracción al reglamento.',
                'advertido' => 'Confirmamos la infracción: el usuario recibió una advertencia.',
                'suspendido' => "Confirmamos la infracción: la cuenta del usuario fue suspendida por {$dias} días.",
                'baneado' => 'Confirmamos la infracción: la cuenta del usuario fue desactivada.',
            };

        $quien = $propiedad ? "\"{$propiedad->titulo}\"" : "a {$reportado?->name}";

        foreach ($pendientes->groupBy('user_id') as $userId => $reportes) {
            Notificacion::create([
                'user_id' => $userId,
                'tipo' => 'reporte_resuelto',
                'titulo' => 'Tu reporte fue revisado',
                'mensaje' => "Gracias por reportar {$quien}. {$resultado}",
                'data' => [
                    'propiedad_id' => $propiedad?->id,
                    'reportado_id' => $propiedad ? null : $reportado?->id,
                    'reporte_ids' => $reportes->pluck('id')->values(),
                    'veredicto' => $veredicto,
                ],
                'leida' => false,
                'leida_at' => null,
            ]);
        }
    }

    /** Notificar al reportado las medidas tomadas. */
    private function notificarReportado(User $reportado, ?Propiedad $propiedad, string $objetivo, string $veredicto, ?string $comentario, array $extra): void
    {
        $dias = StrikeService::DIAS_SUSPENSION;
        $strikes = isset($extra['strikes']) ? " (llevas {$extra['strikes']})" : '';
        $limite = !empty($extra['suspendido'])
            ? " Llegaste al límite de strikes y tu cuenta fue suspendida por {$dias} días."
            : '';

        [$titulo, $mensaje] = $propiedad
            ? match ($veredicto) {
                'exonerado' => ['Tu publicación fue revisada',
                    "Recibimos reportes sobre \"{$propiedad->titulo}\". La revisamos y cumple con el reglamento, así que sigue publicada."],
                'advertido' => ['Advertencia: tu publicación fue dada de baja',
                    "Tu publicación \"{$propiedad->titulo}\" fue dada de baja porque incumple el reglamento y se sumó un strike a tu cuenta{$strikes}.{$limite}"],
                'suspendido' => ['Tu cuenta fue suspendida',
                    "Tu publicación \"{$propiedad->titulo}\" fue dada de baja y tu cuenta quedó suspendida por {$dias} días por incumplir el reglamento."],
                'baneado' => ['Tu cuenta fue desactivada',
                    "Tu publicación \"{$propiedad->titulo}\" fue eliminada y tu cuenta fue desactivada de forma permanente por incumplir gravemente el reglamento."],
            }
            : match ($veredicto) {
                'exonerado' => ['Revisamos un reporte sobre tu cuenta',
                    'Recibimos reportes sobre tu cuenta. Los revisamos y no encontramos una infracción al reglamento.'],
                'advertido' => ['Advertencia en tu cuenta',
                    "Revisamos reportes sobre tu cuenta y confirmamos una infracción al reglamento. Se sumó un strike a tu cuenta{$strikes}.{$limite}"],
                'suspendido' => ['Tu cuenta fue suspendida',
                    "Revisamos reportes sobre tu cuenta y quedó suspendida por {$dias} días por incumplir el reglamento."],
                'baneado' => ['Tu cuenta fue desactivada',
                    'Revisamos reportes sobre tu cuenta y fue desactivada de forma permanente por incumplir gravemente el reglamento.'],
            };

        if ($comentario) {
            $mensaje .= " Comentario del administrador: {$comentario}";
        }

        Notificacion::create([
            'user_id' => $reportado->id,
            'tipo' => 'reporte_veredicto',
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'data' => [
                'propiedad_id' => $propiedad?->id,
                'veredicto' => $veredicto,
            ],
            'leida' => false,
            'leida_at' => null,
        ]);
    }
}
