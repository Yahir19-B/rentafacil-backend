<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index() {
        return response()->json(Categoria::all());
    }

    public function store(Request $request) {
        $data = $request->validate([
            'nombre' => 'required|string|unique:categorias,nombre'
        ]);

        return response()->json(Categoria::create($data), 201);
    }

    public function show(Categoria $categoria) {
        return response()->json($categoria);
    }

    public function update(Request $request, Categoria $categoria) {
        $data = $request->validate([
            'nombre' => 'required|string|unique:categorias,nombre,' . $categoria->id
        ]);

        $categoria->update($data);

        return response()->json($categoria);
    }

    public function destroy(Categoria $categoria) {
        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }
}