<?php

namespace App\Http\Controllers\Api;
use Illuminate\Auth\Events\Verified;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sancion;
use App\Models\User;
use App\Notifications\CodigoReactivacionNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
public function register(Request $request)
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
        'tipo_usuario' => 'required|in:admin,dueno,inquilino',
        'phone' => 'nullable|string|max:20',
        'phone_extra' => 'nullable|string|max:20',
        'sexo' => 'nullable|in:masculino,femenino,otro',
        'fecha_nacimiento' => [
            'required',
            'date',
            'before_or_equal:' . now()->subYears(18)->toDateString(),
        ],
        'es_estudiante' => 'nullable|boolean',
        'foto_perfil' => 'nullable|string',
    ], [
        'email.unique' => 'Este correo ya está registrado.',
        'password.min' => 'La contraseña debe tener mínimo 6 caracteres.',
        'password.regex' => 'La contraseña debe incluir una mayúscula, un número y un carácter especial.',
        'tipo_usuario.required' => 'Selecciona si quieres buscar renta o publicar propiedad.',
        'tipo_usuario.in' => 'El tipo de usuario debe ser dueno o inquilino.',
        'fecha_nacimiento.required' => 'Ingresa tu fecha de nacimiento.',
        'fecha_nacimiento.date' => 'Ingresa una fecha de nacimiento válida.',
        'fecha_nacimiento.before_or_equal' => 'Debes ser mayor de 18 años para registrarte en RentaFácil.',
    ]);

    $role = Role::where('nombre', $data['tipo_usuario'])->firstOrFail();

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'phone' => $data['phone'] ?? null,
        'phone_extra' => $data['phone_extra'] ?? null,
        'sexo' => $data['sexo'] ?? null,
        'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
        'es_estudiante' => $data['es_estudiante'] ?? false,
        'foto_perfil' => $data['foto_perfil'] ?? null,
        'role_id' => $role->id,
        'status' => 'activo',
        'strikes' => 0,
    ]);

    $user->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Cuenta creada correctamente. Revisa tu correo para verificar tu cuenta antes de iniciar sesión.',
        'user' => $user->load('role'),
    ], 201);
}

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role')->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }
        if ($user->status === 'baneado') {
            return response()->json(array_merge(
                $this->respuestaBaneo($user),
                ['account_status' => 'baneado']
            ), 403);
        }
        if ($user->status === 'suspendido') {
            $sancion = $this->suspensionPorInfracciones($user);

            if ($sancion && !$this->reactivarSiVencida($user, $sancion)) {
                return response()->json(array_merge(
                    $this->respuestaSuspensionPorInfracciones($sancion),
                    ['account_status' => 'suspendido']
                ), 403);
            }

            if (!$sancion) {
                return response()->json([
                    'message' => 'Tu cuenta está suspendida.',
                    'account_status' => 'suspendido',
                    'motivo' => 'voluntaria',
                    'reactivable' => true,
                ], 403);
            }

            // La suspensión por strikes ya venció: se reactivó arriba, sigue el login normal.
            $user->refresh();
        }
        if(! $user->hasVerifiedEmail()){
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Debes verificar tu correo antes de iniciar sesión. Te enviamos un nuevo anlace'
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login correcto',
            'token' => $token,
            'user' => $user,
        ]);
    }
public function verifyEmail(
    Request $request,
    string $id,
    string $hash
) {
    $frontendUrl = rtrim(
        env('FRONTEND_URL', 'http://localhost:8100'),
        '/'
    );

    /*
    |--------------------------------------------------------------------------
    | Comprobar que el enlace firmado sea válido
    |--------------------------------------------------------------------------
    */

    if (! $request->hasValidSignature()) {
        return redirect(
            $frontendUrl . '/login?verification=expired'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buscar usuario
    |--------------------------------------------------------------------------
    */

    $user = User::find($id);

    if (! $user) {
        return redirect(
            $frontendUrl . '/login?verification=user-not-found'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validar que el hash corresponda al correo
    |--------------------------------------------------------------------------
    */

    $expectedHash = sha1(
        $user->getEmailForVerification()
    );

    if (! hash_equals($expectedHash, $hash)) {
        return redirect(
            $frontendUrl . '/login?verification=invalid'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verificar si ya estaba validado
    |--------------------------------------------------------------------------
    */

    if ($user->hasVerifiedEmail()) {
        return redirect(
            $frontendUrl . '/login?verification=already-verified'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Marcar correo como verificado
    |--------------------------------------------------------------------------
    */

    $user->markEmailAsVerified();

    event(new Verified($user));

    return redirect(
        $frontendUrl . '/login?verification=success'
    );
}
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $status = Password::sendResetLink([
            'email' => $data['email'],
        ]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Espera un momento antes de solicitar otro enlace.',
            ], 429);
        }

        // Se devuelve el mismo mensaje aunque el correo no exista para no revelar cuentas registradas.
        return response()->json([
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:6',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'token.required' => 'El enlace de recuperación no contiene un token válido.',
            'email.required' => 'El enlace de recuperación no contiene un correo válido.',
            'email.email' => 'El correo del enlace no es válido.',
            'password.required' => 'Ingresa una contraseña nueva.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener mínimo 6 caracteres.',
            'password.regex' => 'La contraseña debe incluir una mayúscula, un número y un carácter especial.',
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Cierra las sesiones abiertas para proteger la cuenta.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'El enlace es inválido, ya fue utilizado o expiró. Solicita uno nuevo.',
            ], 422);
        }

        return response()->json([
            'message' => 'Contraseña restablecida correctamente. Ya puedes iniciar sesión.',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user()->load('role'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'phone_extra' => 'nullable|string|max:20',
            'sexo' => 'nullable|in:masculino,femenino,otro',
            'fecha_nacimiento' => 'nullable|date',
            'es_estudiante' => 'nullable|boolean',
            'foto_perfil' => 'nullable|string|max:5000',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user->load('role'),
        ]);
    }

    public function cambiarTipoUsuario(Request $request)
    {
        $data = $request->validate([
            'tipo_usuario' => 'required|in:dueno,inquilino',
        ]);

        $user = $request->user()->load('role');

        if ($user->role?->nombre === 'admin') {
            return response()->json([
                'message' => 'Una cuenta de administrador no puede cambiar de tipo de usuario.'
            ], 422);
        }

        $role = Role::where('nombre', $data['tipo_usuario'])->firstOrFail();

        $user->update(['role_id' => $role->id]);

        return response()->json([
            'message' => 'Tipo de usuario actualizado correctamente',
            'user' => $user->load('role'),
        ]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:6',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta'], 422);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Contraseña actualizada correctamente. Inicia sesión de nuevo.']);
    }

    public function suspendAccount(Request $request)
    {
        $data = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 422);
        }

        $user->update(['status' => 'suspendido']);
        $user->tokens()->delete();

        return response()->json(['message' => 'Cuenta suspendida correctamente']);
    }

    private function suspensionPorInfracciones(User $user): ?Sancion
    {
        return $user->sanciones()
            ->where('tipo', 'suspension')
            ->where('activa', true)
            ->latest()
            ->first();
    }

    /**
     * Si la suspensión por strikes ya venció (pasaron los 3 días), reactiva
     * la cuenta automáticamente y desactiva la sanción. Devuelve true si
     * reactivó, false si la sanción todavía sigue vigente.
     */
    private function reactivarSiVencida(User $user, Sancion $sancion): bool
    {
        if (!$sancion->fecha_fin || $sancion->fecha_fin->isFuture()) {
            return false;
        }

        $user->update(['status' => 'activo', 'strikes' => 0]);
        $sancion->update(['activa' => false]);

        return true;
    }

    private function diasRestantes(Sancion $sancion): string
    {
        if (!$sancion->fecha_fin) {
            return 'unos días';
        }

        $horas = max(1, now()->diffInHours($sancion->fecha_fin, false));
        $dias = (int) ceil($horas / 24);

        return $dias === 1 ? '1 día' : "{$dias} días";
    }

    private function contactoAdmin(): ?array
    {
        $admin = User::whereHas('role', function ($query) {
            $query->where('nombre', 'admin');
        })->first(['name', 'email']);

        return $admin ? ['name' => $admin->name, 'email' => $admin->email] : null;
    }

    /**
     * Payload común para cuando una suspensión por infracciones sigue
     * vigente: incluye el contacto real del admin en el mensaje, no solo
     * en el campo "soporte", para que se muestre tal cual en el frontend.
     */
    private function respuestaSuspensionPorInfracciones(Sancion $sancion): array
    {
        $soporte = $this->contactoAdmin();

        $mensajeSoporte = $soporte
            ? "Si crees que esto es un error, contacta a soporte: {$soporte['name']} ({$soporte['email']})."
            : 'Si crees que esto es un error, contacta al equipo de soporte.';

        return [
            'message' => 'Tu cuenta fue suspendida por acumular infracciones al reglamento. '
                . 'Se reactivará automáticamente en ' . $this->diasRestantes($sancion) . '. '
                . $mensajeSoporte,
            'motivo' => 'infracciones',
            'reactivable' => false,
            'fecha_reactivacion' => $sancion->fecha_fin,
            'soporte' => $soporte,
        ];
    }

    private function ultimoBaneo(User $user): ?Sancion
    {
        return $user->sanciones()
            ->where('tipo', 'baneo')
            ->latest()
            ->first();
    }

    /**
     * Payload para cuando la cuenta está baneada permanentemente: incluye
     * el motivo real que registró el admin (si existe) y su contacto, para
     * que el usuario sepa por qué fue baneado y a quién escribirle.
     */
    private function respuestaBaneo(User $user): array
    {
        $sancion = $this->ultimoBaneo($user);
        $soporte = $this->contactoAdmin();

        $mensajeSoporte = $soporte
            ? "Si crees que esto es un error, contacta a soporte: {$soporte['name']} ({$soporte['email']})."
            : 'Si crees que esto es un error, contacta al equipo de soporte.';

        $mensajeMotivo = $sancion?->motivo
            ? " Motivo: {$sancion->motivo}."
            : '';

        return [
            'message' => 'Tu cuenta fue baneada permanentemente por incumplir el reglamento de la plataforma.'
                . $mensajeMotivo . ' ' . $mensajeSoporte,
            'motivo' => $sancion?->motivo,
            'reactivable' => false,
            'soporte' => $soporte,
        ];
    }

    private const MINUTOS_VIGENCIA_CODIGO_REACTIVACION = 15;

    public function reactivateAccount(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role')->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if ($user->status !== 'suspendido') {
            return response()->json(['message' => 'La cuenta no está suspendida'], 422);
        }

        $sancion = $this->suspensionPorInfracciones($user);

        if ($sancion) {
            if (!$this->reactivarSiVencida($user, $sancion)) {
                return response()->json(
                    $this->respuestaSuspensionPorInfracciones($sancion),
                    403
                );
            }

            // Suspensión por strikes ya vencida: se reactivó sola, sin pedir código.
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'message' => 'Cuenta reactivada correctamente',
                'token' => $token,
                'user' => $user,
            ]);
        }

        // Suspensión voluntaria: se manda un código de verificación por correo
        // antes de reactivar, en vez de reactivar directo con la contraseña.
        $codigo = (string) random_int(100000, 999999);

        $user->forceFill([
            'codigo_reactivacion' => $codigo,
            'codigo_reactivacion_expira_en' => now()->addMinutes(self::MINUTOS_VIGENCIA_CODIGO_REACTIVACION),
        ])->save();

        $user->notify(
            new CodigoReactivacionNotification($codigo, self::MINUTOS_VIGENCIA_CODIGO_REACTIVACION)
        );

        return response()->json([
            'message' => 'Te enviamos un código a tu correo para confirmar la reactivación.',
            'requiere_codigo' => true,
        ]);
    }

    public function confirmarReactivacion(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'codigo' => 'required|string',
        ]);

        $user = User::with('role')->where('email', $data['email'])->first();

        if (!$user || $user->status !== 'suspendido') {
            return response()->json(['message' => 'No hay una reactivación pendiente para esta cuenta.'], 422);
        }

        $codigoValido = $user->codigo_reactivacion
            && hash_equals($user->codigo_reactivacion, trim($data['codigo']));

        $codigoVigente = $user->codigo_reactivacion_expira_en
            && $user->codigo_reactivacion_expira_en->isFuture();

        if (!$codigoValido || !$codigoVigente) {
            return response()->json(['message' => 'El código es incorrecto o ya expiró.'], 422);
        }

        $user->forceFill([
            'status' => 'activo',
            'codigo_reactivacion' => null,
            'codigo_reactivacion_expira_en' => null,
        ])->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Cuenta reactivada correctamente',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function aceptarTerminos(Request $request)
    {
        $user = $request->user();

        $user->update(['terminos_aceptados_at' => now()]);

        return response()->json([
            'message' => 'Términos aceptados correctamente.',
            'user' => $user->load('role'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    // Google
    public function googleRedirect()
    {
        /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
        $provider = Socialite::driver('google');

        return $provider->stateless()->redirect();
    }

    public function googleCallback()
    {
    try {
        /** @var \Laravel\Socialite\Two\AbstractProvider $provider */
        $provider = Socialite::driver('google');

        $googleUser = $provider->stateless()->user();

        $role = Role::where('nombre', 'inquilino')->firstOrFail();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Usuario Google',
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(24)),
                'foto_perfil' => $googleUser->getAvatar(),
                'google_id' => $googleUser->getId(),
                'auth_provider' => 'google',
                'role_id' => $role->id,
                'status' => 'activo',
                'strikes' => 0,
            ]);
        } else {
            $user->update([
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'foto_perfil' => $user->foto_perfil ?: $googleUser->getAvatar(),
                'auth_provider' => $user->auth_provider ?: 'google',
            ]);
        }

        if ($user->status === 'baneado') {
            return redirect(env('FRONTEND_URL') . '/login?error=cuenta_baneada');
        }

        if ($user->status === 'suspendido') {
            return redirect(env('FRONTEND_URL') . '/login?error=cuenta_suspendida');
        }

        $user->load('role');

        $token = $user->createToken('api-token')->plainTextToken;

        return redirect(
            env('FRONTEND_URL') .
            '/auth/callback?token=' . urlencode($token) .
            '&role=' . urlencode($user->role->nombre) .
            '&terminos_aceptados=' . ($user->terminos_aceptados_at ? '1' : '0')
        );

    } catch (\Throwable $e) {
        return redirect(env('FRONTEND_URL') . '/login?error=google_login_failed');
    }
}
}
