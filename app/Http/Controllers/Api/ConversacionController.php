<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use Illuminate\Http\Request;

class ConversacionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversaciones = Conversacion::with([
            'propiedad',
            'userUno',
            'userDos',
            'mensajes' => function ($query) {
                $query->latest();
            }
        ])
        ->where('user_uno_id', $user->id)
        ->orWhere('user_dos_id', $user->id)
        ->latest()
        ->get();

        return response()->json($conversaciones);
    }

    public function store(Request $request)
    {
        $request->validate([
            'propiedad_id' => 'nullable|exists:propiedades,id',
            'user_dos_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();

        // Una sola conversación por par de usuarios, sin importar la propiedad.
        $conversacion = Conversacion::where(function ($query) use ($user, $request) {
                $query->where(function ($q) use ($user, $request) {
                    $q->where('user_uno_id', $user->id)
                      ->where('user_dos_id', $request->user_dos_id);
                })
                ->orWhere(function ($q) use ($user, $request) {
                    $q->where('user_uno_id', $request->user_dos_id)
                      ->where('user_dos_id', $user->id);
                });
            })
            ->first();

        if (!$conversacion) {
            $conversacion = Conversacion::create([
                'propiedad_id' => $request->propiedad_id,
                'user_uno_id' => $user->id,
                'user_dos_id' => $request->user_dos_id,
            ]);
        }

        return response()->json([
            'message' => 'Conversación creada correctamente',
            'conversacion' => $conversacion
        ]);
    }

    public function show($id)
    {
        $conversacion = Conversacion::with([
            'propiedad',
            'userUno',
            'userDos',
            'mensajes.emisor'
        ])->findOrFail($id);

        return response()->json($conversacion);
    }
}