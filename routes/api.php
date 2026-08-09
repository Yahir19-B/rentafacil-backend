<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\CategoriaController;
use App\Http\Controllers\Api\PropiedadController;
use App\Http\Controllers\Api\PropiedadImagenController;
use App\Http\Controllers\Api\ModeracionController;
use App\Http\Controllers\Api\RevisionAdminController;
use App\Http\Controllers\Api\SancionController;
use App\Http\Controllers\Api\FavoritoController;
use App\Http\Controllers\Api\MensajeController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\SolicitudRentaController;
use App\Http\Controllers\Api\ComentarioController;
use App\Http\Controllers\Api\CalificacionController;
use App\Http\Controllers\Api\ConversacionController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\SolicitudAmistadController;
use App\Http\Controllers\Api\AdminPropiedadController;
use App\Http\Controllers\Api\AdminUsuarioController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminModeracionController;
use App\Http\Controllers\Api\AdminRevisionContenidoController;
use App\Http\Controllers\Api\AdminConfiguracionController;
use App\Http\Controllers\Api\AdminAdministradoresController;

Route::get('/', function () {
    return response()->json([
        'message' => 'API Rentas funcionando correctamente'
    ]);
});

/*
|--------------------------------------------------------------------------
| Auth público
|--------------------------------------------------------------------------
*/

Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:10,1');
Route::post('/reactivate-account', [AuthController::class, 'reactivateAccount']);
Route::post('/reactivate-account/confirmar', [AuthController::class, 'confirmarReactivacion']);

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Route::get('/propiedades', [PropiedadController::class, 'index']);
Route::get('/propiedades/{propiedad}', [PropiedadController::class, 'show']);

Route::get('/categorias', [CategoriaController::class, 'index']);

Route::get('/comentarios', [ComentarioController::class, 'index']);
Route::get('/calificaciones', [CalificacionController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Perfil
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/suspend-account', [AuthController::class, 'suspendAccount']);
    Route::post('/aceptar-terminos', [AuthController::class, 'aceptarTerminos']);
    Route::put('/cambiar-tipo-usuario', [AuthController::class, 'cambiarTipoUsuario']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Vistas de propiedades
    |--------------------------------------------------------------------------
    */

    Route::post('/propiedades/{propiedad}/vista', [
        PropiedadController::class,
        'registrarVista'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Propiedades - Dueño y Admin
    |--------------------------------------------------------------------------
    */

Route::middleware('role:dueno,admin')->group(function () {

    Route::get('/mis-propiedades', [
        PropiedadController::class,
        'misPropiedades'
    ]);

    Route::get('/mis-propiedades/{propiedad}', [
        PropiedadController::class,
        'miPropiedad'
    ]);

    Route::post('/propiedades', [
        PropiedadController::class,
        'store'
    ]);

    Route::put('/propiedades/{propiedad}', [
        PropiedadController::class,
        'update'
    ]);

    Route::patch('/propiedades/{propiedad}', [
        PropiedadController::class,
        'update'
    ]);

    Route::delete('/propiedades/{propiedad}', [
        PropiedadController::class,
        'destroy'
    ]);

    Route::apiResource(
        'propiedad-imagenes',
        PropiedadImagenController::class
    )->parameters([
        'propiedad-imagenes' => 'propiedadImagen'
    ]);
});

    /*
    |--------------------------------------------------------------------------
    | Favoritos
    |--------------------------------------------------------------------------
    */

    Route::apiResource('favoritos', FavoritoController::class);

    /*
    |--------------------------------------------------------------------------
    | Usuarios (búsqueda y perfil público)
    |--------------------------------------------------------------------------
    */

    Route::get('/usuarios/buscar', [UsuarioController::class, 'buscar']);
    Route::get('/usuarios/contacto-soporte', [UsuarioController::class, 'contactoSoporte']);
    Route::get('/usuarios/{usuario}/estado-amistad', [SolicitudAmistadController::class, 'estado']);
    Route::post('/usuarios/{usuario}/solicitud-amistad', [SolicitudAmistadController::class, 'enviar']);
    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show']);

    Route::patch('/solicitudes-amistad/{solicitud}/aceptar', [SolicitudAmistadController::class, 'aceptar']);
    Route::patch('/solicitudes-amistad/{solicitud}/rechazar', [SolicitudAmistadController::class, 'rechazar']);

    /*
    |--------------------------------------------------------------------------
    | Conversaciones y mensajes
    |--------------------------------------------------------------------------
    */

    Route::apiResource('conversaciones', ConversacionController::class)
        ->only(['index', 'store', 'show']);

    Route::get('/conversaciones/{conversacion}/mensajes', [
        MensajeController::class,
        'mensajesPorConversacion'
    ]);

    Route::put('/mensajes/{mensaje}/leido', [
        MensajeController::class,
        'marcarComoLeido'
    ]);

    Route::apiResource('mensajes', MensajeController::class);

    /*
    |--------------------------------------------------------------------------
    | Solicitudes de renta
    |--------------------------------------------------------------------------
    */

    Route::apiResource('solicitudes-renta', SolicitudRentaController::class);

    /*
    |--------------------------------------------------------------------------
    | Comentarios y calificaciones
    |--------------------------------------------------------------------------
    */

    Route::apiResource('comentarios', ComentarioController::class)
        ->except(['index']);

    Route::apiResource('calificaciones', CalificacionController::class)
        ->except(['index']);

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    */

    Route::apiResource('reportes', ReporteController::class)
        ->only(['index', 'store', 'show']);

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    | Estas rutas especiales van antes del apiResource para evitar conflictos.
    |--------------------------------------------------------------------------
    */

    Route::get('/notificaciones/no-leidas', [
        NotificacionController::class,
        'noLeidas'
    ]);

    Route::put('/notificaciones/marcar-todas/leidas', [
        NotificacionController::class,
        'marcarTodasComoLeidas'
    ]);

    Route::put('/notificaciones/{notificacion}/leida', [
        NotificacionController::class,
        'marcarComoLeida'
    ]);

    Route::apiResource('notificaciones', NotificacionController::class);

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/admin/moderacion/estadisticas', [AdminModeracionController::class, 'estadisticas']);
        Route::get('/admin/configuracion', [AdminConfiguracionController::class, 'show']);
        Route::put('/admin/configuracion', [AdminConfiguracionController::class, 'update']);

        Route::get('/admin/usuarios', [AdminUsuarioController::class, 'index']);
        Route::get('/admin/usuarios/{usuario}', [AdminUsuarioController::class, 'show']);
        Route::post('/admin/usuarios/{usuario}/banear', [AdminUsuarioController::class, 'banear']);
        Route::post('/admin/usuarios/{usuario}/desbanear', [AdminUsuarioController::class, 'desbanear']);

        Route::get('/admin/revision-contenido', [AdminRevisionContenidoController::class, 'index']);
        Route::post('/admin/revision-contenido/{moderacion}/aprobar', [AdminRevisionContenidoController::class, 'aprobar']);
        Route::post('/admin/revision-contenido/{moderacion}/banear', [AdminRevisionContenidoController::class, 'banear']);
        Route::post('/admin/revision-contenido/{moderacion}/suspender', [AdminRevisionContenidoController::class, 'suspender']);
        Route::post('/admin/revision-contenido/{moderacion}/banear-cuenta', [AdminRevisionContenidoController::class, 'banearCuenta']);

        Route::get('/admin/propiedades', [AdminPropiedadController::class, 'index']);
        Route::post('/admin/propiedades/{propiedad}/banear', [AdminPropiedadController::class, 'banear']);

        Route::get('/admin/administradores', [AdminAdministradoresController::class, 'index']);
        Route::post('/admin/administradores', [AdminAdministradoresController::class, 'store']);
        Route::delete('/admin/administradores/{usuario}', [AdminAdministradoresController::class, 'destroy']);

        Route::apiResource('roles', RoleController::class);

        Route::apiResource('categorias', CategoriaController::class)
            ->except(['index']);

        Route::apiResource('moderaciones', ModeracionController::class);
        Route::apiResource('revisiones-admin', RevisionAdminController::class);
        Route::apiResource('sanciones', SancionController::class);

        Route::apiResource('reportes', ReporteController::class)
            ->only(['update', 'destroy']);
    });
});