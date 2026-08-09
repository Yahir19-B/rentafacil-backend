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
        ]);

        $texto = trim($data['q']);

        $usuarios = User::with('role')
            ->where('id', '!=', Auth::id())
            ->whereNotIn('status', ['suspendido', 'baneado'])
            ->whereHas('role', function ($query) {
                $query->where('nombre', '!=', 'admin');
            })
            ->where(function ($query) use ($texto) {
                $query->where('name', 'like', "%{$texto}%")
                    ->orWhere('email', 'like', "%{$texto}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'foto_perfil', 'role_id']);

        return response()->json($usuarios);
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

    public function show(User $usuario)
    {
        if (in_array($usuario->status, ['suspendido', 'baneado'])) {
            return response()->json([
                'message' => 'Este perfil no está disponible.'
            ], 404);
        }

        $usuario->load('role');

        $perfil = [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'foto_perfil' => $usuario->foto_perfil,
            'es_estudiante' => $usuario->es_estudiante,
            'role' => $usuario->role,
            'created_at' => $usuario->created_at,
        ];

        if ($usuario->role?->nombre === 'dueno') {
            $perfil['propiedades'] = $usuario->propiedades()
                ->with(['categoria', 'imagenes'])
                ->where('estado_publicacion', 'aprobada')
                ->where('disponible', true)
                ->orderByDesc('created_at')
                ->get();
        }

        return response()->json($perfil);
    }
}
