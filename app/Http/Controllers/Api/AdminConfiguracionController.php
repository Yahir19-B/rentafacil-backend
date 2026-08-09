<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class AdminConfiguracionController extends Controller
{
    public function show()
    {
        return response()->json(Configuracion::actual());
    }

    /**
     * El límite de strikes queda fijo en 3: no se acepta ningún cambio,
     * ni siquiera llamando a este endpoint directamente.
     */
    public function update(Request $request)
    {
        return response()->json([
            'message' => 'El límite de strikes es fijo y no se puede modificar.',
        ], 403);
    }
}
