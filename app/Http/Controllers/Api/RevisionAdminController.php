<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RevisionAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevisionAdminController extends Controller
{
    public function index() {
        return response()->json(
            RevisionAdmin::with(['moderacion', 'admin'])->latest()->get()
        );
    }

    public function store(Request $request) {
        $data = $request->validate([
            'moderacion_id' => 'required|exists:moderaciones,id',
            'decision' => 'required|in:aprobar,rechazar,banear,advertir',
            'comentario' => 'nullable|string'
        ]);

        $data['admin_id'] = Auth::id();

        return response()->json(RevisionAdmin::create($data), 201);
    }

    public function show(RevisionAdmin $revisionAdmin) {
        return response()->json(
            $revisionAdmin->load(['moderacion', 'admin'])
        );
    }

    public function update(Request $request, RevisionAdmin $revisionAdmin) {
        $data = $request->validate([
            'decision' => 'sometimes|in:aprobar,rechazar,banear,advertir',
            'comentario' => 'nullable|string'
        ]);

        $revisionAdmin->update($data);

        return response()->json($revisionAdmin);
    }

    public function destroy(RevisionAdmin $revisionAdmin) {
        $revisionAdmin->delete();

        return response()->json(['message' => 'Revisión eliminada']);
    }
}