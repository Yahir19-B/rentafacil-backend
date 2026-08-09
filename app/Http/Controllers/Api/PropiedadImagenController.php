<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moderacion;
use App\Models\Propiedad;
use App\Models\PropiedadImagen;
use App\Models\User;
use App\Notifications\ImagenSospechosaNotification;
use App\Services\ModeracionImagenService;
use Illuminate\Http\Request;

class PropiedadImagenController extends Controller
{
    public function __construct(
        private readonly ModeracionImagenService $moderacionImagen,
    ) {
    }

    public function index() {
        return response()->json(PropiedadImagen::all());
    }

    public function store(Request $request) {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'firebase_path' => 'required|string',
            'url' => 'required|string',
            'orden' => 'nullable|integer',
            'estado' => 'nullable|in:en_revision,aprobada,rechazada'
        ]);

        $resultado = $this->moderacionImagen->analizarImagen($data['url']);

        $data['estado'] = $data['estado'] ?? 'en_revision';

        $imagen = PropiedadImagen::create($data);

        if (!$resultado['aprobada']) {
            $motivo = 'Posible contenido inapropiado (sangre, +18 o violento) detectado en las imágenes. Pendiente de revisión manual.';

            Moderacion::create([
                'propiedad_id' => $data['propiedad_id'],
                'user_id' => $request->user()->id,
                'tipo' => 'imagen',
                'resultado' => 'sospechoso',
                'etiquetas_detectadas' => $resultado['etiquetas'],
                'motivo' => $motivo,
                'proveedor_ia' => 'google_vision',
                'estado' => 'pendiente',
            ]);

            $propiedadAfectada = Propiedad::find($data['propiedad_id']);

            if ($propiedadAfectada && $propiedadAfectada->estado_publicacion === 'aprobada') {
                $propiedadAfectada->update(['estado_publicacion' => 'en_revision']);
            }

            if ($propiedadAfectada) {
                $this->notificarAdminsImagenSospechosa($propiedadAfectada, $motivo);
            }
        }

        return response()->json($imagen, 201);
    }

    private function notificarAdminsImagenSospechosa(Propiedad $propiedad, string $motivo): void
    {
        $propiedad->loadMissing('user');

        $admins = User::whereHas('role', fn ($q) => $q->where('nombre', 'admin'))->get();

        foreach ($admins as $admin) {
            $admin->notify(new ImagenSospechosaNotification($propiedad, $motivo));
        }
    }

    public function show(PropiedadImagen $propiedadImagen) {
        return response()->json($propiedadImagen);
    }

    public function update(Request $request, PropiedadImagen $propiedadImagen) {
        $data = $request->validate([
            'firebase_path' => 'sometimes|string',
            'url' => 'sometimes|string',
            'orden' => 'nullable|integer',
            'estado' => 'sometimes|in:en_revision,aprobada,rechazada'
        ]);

        $propiedadImagen->update($data);

        return response()->json($propiedadImagen);
    }

    public function destroy(PropiedadImagen $propiedadImagen) {
        $propiedadImagen->delete();

        return response()->json(['message' => 'Imagen eliminada']);
    }
}