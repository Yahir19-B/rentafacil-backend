<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu cuenta de administrador</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f5; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

        <div style="background:#166534; padding:30px; text-align:center;">
            @if (!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="RentaFácil" style="width:120px; height:auto; margin-bottom:15px;">
            @endif

            <h1 style="color:#ffffff; margin:0; font-size:26px;">
                Se creó tu cuenta de administrador
            </h1>
        </div>

        <div style="padding:35px; color:#1f2937;">
            <h2 style="margin-top:0;">
                Hola {{ $user->name }}
            </h2>

            <p style="font-size:16px; line-height:1.6;">
                Ya tienes acceso al panel de administración de RentaFácil con estas credenciales:
            </p>

            <table style="width:100%; background:#f3f4f6; border-radius:10px; padding:6px; margin:18px 0;">
                <tr>
                    <td style="padding:12px 18px; font-size:15px;">
                        <strong>Correo:</strong> {{ $email }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 18px; font-size:15px;">
                        <strong>Contraseña:</strong> {{ $password }}
                    </td>
                </tr>
            </table>

            <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                Por seguridad, te recomendamos cambiar tu contraseña desde tu perfil en cuanto inicies sesión.
            </p>
        </div>

        <div style="background:#f3f4f6; padding:18px; text-align:center; color:#6b7280; font-size:13px;">
            © {{ date('Y') }} RentaFácil. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
