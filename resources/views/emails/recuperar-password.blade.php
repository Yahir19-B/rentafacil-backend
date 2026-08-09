<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablece tu contraseña</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f5; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

        <div style="background:#166534; padding:30px; text-align:center;">
            @if (!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="RentaFácil" style="width:120px; height:auto; margin-bottom:15px;">
            @endif

            <h1 style="color:#ffffff; margin:0; font-size:26px;">
                Recupera tu contraseña
            </h1>
        </div>

        <div style="padding:35px; color:#1f2937;">
            <h2 style="margin-top:0;">
                Hola {{ $user->name }}
            </h2>

            <p style="font-size:16px; line-height:1.6;">
                Recibimos una solicitud para cambiar la contraseña de tu cuenta de RentaFácil.
                Presiona el siguiente botón para crear una contraseña nueva.
            </p>

            <div style="text-align:center; margin:35px 0;">
                <a href="{{ $url }}"
                   style="background:#166534; color:#ffffff; padding:14px 28px; text-decoration:none; border-radius:10px; font-weight:bold; display:inline-block;">
                    Crear contraseña nueva
                </a>
            </div>

            <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                Este enlace vence en {{ $expirationMinutes }} minutos y solo puede utilizarse una vez.
            </p>

            <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                Si tú no solicitaste este cambio, ignora el correo. Tu contraseña actual seguirá funcionando.
            </p>
        </div>

        <div style="background:#f3f4f6; padding:18px; text-align:center; color:#6b7280; font-size:13px;">
            © {{ date('Y') }} RentaFácil. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
