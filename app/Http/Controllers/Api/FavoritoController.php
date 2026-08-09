<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoritoController extends Controller
{
    public function index() {
        return response()->json(
            Favorito::with('propiedad.imagenes')
                ->where('user_id', Auth::id())
                ->latest()
                ->get()
        );
    }

    public function store(Request $request) {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id'
        ]);

        $favorito = Favorito::firstOrCreate([
            'user_id' => Auth::id(),
            'propiedad_id' => $data['propiedad_id']
        ]);

        return response()->json($favorito, 201);
    }

    public function show(Favorito $favorito) {
        return response()->json($favorito->load('propiedad'));
    }

    public function update(Request $request, Favorito $favorito) {
        return response()->json([
            'message' => 'Los favoritos no necesitan actualización'
        ]);
    }

    public function destroy(Favorito $favorito) {
        $favorito->delete();

        return response()->json(['message' => 'Favorito eliminado']);
    }
}