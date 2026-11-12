<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller
{
    public function buscar(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'rol' => 'nullable|in:dueno,inquilino',
        ]);

        $texto = trim($data['q']);

        $usuarios = User::with('role')
            ->withCount([
                'propiedades as propiedades_count' => function ($query) {
                    $query->where('estado_publicacion', 'aprobada')
                        ->where('disponible', true);
                },
                'avisos',
            ])
            ->where('id', '!=', Auth::id())
            ->whereNotIn('status', ['suspendido', 'baneado'])
            ->whereHas('role', function ($query) use ($data) {
                $query->where('nombre', '!=', 'admin');

                if (!empty($data['rol'])) {
                    $query->where('nombre', $data['rol']);
                }
            })
            ->where(function ($query) use ($texto) {
                $query->where('name', 'like', "%{$texto}%")
                    ->orWhere('email', 'like', "%{$texto}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        // Solo se devuelve lo que el usuario permite mostrar.
        return response()->json($usuarios->map(function (User $usuario) {
            $privacidad = $usuario->privacidad;
            $publico = $privacidad['visibilidad_muro'] === 'todos';

            return [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'foto_perfil' => $usuario->foto_perfil,
                'role' => $usuario->role,
                'biografia' => $publico ? $usuario->biografia : null,
                'ciudad' => $publico && $privacidad['mostrar_ciudad'] ? $usuario->ciudad : null,
                'muro_privado' => !$publico,
                'propiedades_count' => $publico && $privacidad['mostrar_propiedades']
                    ? $usuario->propiedades_count
                    : null,
                'avisos_count' => $publico && $privacidad['mostrar_avisos']
                    ? $usuario->avisos_count
                    : null,
            ];
        }));
    }

    /**
     * Datos de contacto de soporte (el admin) para el apartado
     * de Ayuda y soporte. La búsqueda normal excluye a los
     * administradores, así que este es el único punto de acceso.
     */
    public function contactoSoporte()
    {
        $admin = User::whereHas('role', function ($query) {
            $query->where('nombre', 'admin');
        })->first(['id', 'name', 'email', 'foto_perfil']);

        if (!$admin) {
            return response()->json([
                'message' => 'No hay una cuenta de soporte disponible.'
            ], 404);
        }

        return response()->json($admin);
    }

    /**
     * Muro público del usuario. Respeta su configuración de privacidad:
     * quién puede ver el muro (todos o solo amigos) y qué secciones
     * muestra. El dueño del muro y los administradores lo ven completo.
     */
    public function show(Request $request, User $usuario)
    {
        if (in_array($usuario->status, ['suspendido', 'baneado'])) {
            return response()->json([
                'message' => 'Este perfil no está disponible.'
            ], 404);
        }

        $usuario->load('role');

        $visitante = $request->user()->loadMissing('role');
        $esPropio = $visitante->id === $usuario->id;
        $esAdmin = $visitante->role?->nombre === 'admin';
        $verTodo = $esPropio || $esAdmin;

        $privacidad = $usuario->privacidad;
        $idsAmigos = $usuario->idsAmigos();
        $esAmigo = in_array($visitante->id, $idsAmigos, true);

        $puedeVerMuro = $verTodo
            || $privacidad['visibilidad_muro'] === 'todos'
            || $esAmigo;

        $mostrar = fn (string $opcion): bool =>
            $puedeVerMuro && ($verTodo || $privacidad[$opcion]);

        $perfil = [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'foto_perfil' => $usuario->foto_perfil,
            'foto_portada' => $puedeVerMuro ? $usuario->foto_portada : null,
            'role' => $usuario->role,
            'es_propio' => $esPropio,
            'muro_privado' => !$puedeVerMuro,
            'biografia' => $puedeVerMuro ? $usuario->biografia : null,
            'ciudad' => $mostrar('mostrar_ciudad') ? $usuario->ciudad : null,
            'email' => $mostrar('mostrar_email') ? $usuario->email : null,
            'phone' => $mostrar('mostrar_telefono') ? $usuario->phone : null,
            'es_estudiante' => $mostrar('mostrar_estudiante') ? $usuario->es_estudiante : null,
            'created_at' => $mostrar('mostrar_miembro_desde') ? $usuario->created_at : null,
        ];

        if ($mostrar('mostrar_propiedades')) {
            $perfil['propiedades'] = $usuario->propiedades()
                ->with(['categoria', 'imagenes'])
                ->where('estado_publicacion', 'aprobada')
                ->where('disponible', true)
                ->orderByDesc('created_at')
                ->get();
        }

        if ($mostrar('mostrar_avisos')) {
            $perfil['avisos'] = $usuario->avisos()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        if ($mostrar('mostrar_amigos')) {
            $perfil['amigos'] = [
                'total' => count($idsAmigos),
                'muestra' => User::whereIn('id', $idsAmigos)
                    ->whereNotIn('status', ['suspendido', 'baneado'])
                    ->inRandomOrder()
                    ->limit(9)
                    ->get(['id', 'name', 'foto_perfil']),
            ];
        }

        // Solo el dueño del muro recibe su configuración para editarla.
        if ($esPropio) {
            $perfil['privacidad'] = $privacidad;
        }

        return response()->json($perfil);
    }
}
