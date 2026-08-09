<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CredencialesAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAdministradoresController extends Controller
{
    public function index()
    {
        $administradores = User::whereHas('role', function ($query) {
            $query->where('nombre', 'admin');
        })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'foto_perfil', 'created_at']);

        return response()->json($administradores);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:6',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'email.unique' => 'Este correo ya está registrado.',
            'password.min' => 'La contraseña debe tener mínimo 6 caracteres.',
            'password.regex' => 'La contraseña debe incluir una mayúscula, un número y un carácter especial.',
        ]);

        $rolAdmin = Role::where('nombre', 'admin')->firstOrFail();

        $administrador = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $rolAdmin->id,
            'status' => 'activo',
            'terminos_aceptados_at' => now(),
        ]);

        // email_verified_at no es mass-assignable (queda fuera de $fillable
        // a propósito), así que se marca aparte.
        $administrador->forceFill(['email_verified_at' => now()])->save();

        $administrador->notify(
            new CredencialesAdminNotification($data['email'], $data['password'])
        );

        return response()->json(
            $administrador->only(['id', 'name', 'email', 'foto_perfil', 'created_at']),
            201
        );
    }

    public function destroy(User $usuario)
    {
        if ($usuario->role?->nombre !== 'admin') {
            return response()->json(['message' => 'Este usuario no es un administrador.'], 422);
        }

        if ($usuario->id === Auth::id()) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta de administrador.'], 422);
        }

        $totalAdmins = User::whereHas('role', function ($query) {
            $query->where('nombre', 'admin');
        })->count();

        if ($totalAdmins <= 1) {
            return response()->json(['message' => 'Debe quedar al menos un administrador.'], 422);
        }

        $usuario->tokens()->delete();
        $usuario->delete();

        return response()->json(['message' => 'Administrador eliminado correctamente.']);
    }
}
