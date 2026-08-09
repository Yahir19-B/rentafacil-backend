<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index() {
        return response()->json(Role::all());
    }

    public function store(Request $request) {
        $data = $request->validate([
            'nombre' => 'required|string|unique:roles,nombre'
        ]);

        return response()->json(Role::create($data), 201);
    }

    public function show(Role $role) {
        return response()->json($role);
    }

    public function update(Request $request, Role $role) {
        $data = $request->validate([
            'nombre' => 'required|string|unique:roles,nombre,' . $role->id
        ]);

        $role->update($data);

        return response()->json($role);
    }

    public function destroy(Role $role) {
        $role->delete();

        return response()->json(['message' => 'Rol eliminado']);
    }
}