<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imagen pendiente de revisión</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f5; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

        <div style="background:#b45f2b; padding:30px; text-align:center;">
            @if (!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="RentaFácil" style="width:120px; height:auto; margin-bottom:15px;">
            @endif

            <h1 style="color:#ffffff; margin:0; font-size:26px;">
                Imagen pendiente de revisión
            </h1>
        </div>

        <div style="padding:35px; color:#1f2937;">
            <h2 style="margin-top:0;">
                Hola {{ $admin->name }}
            </h2>

            <p style="font-size:16px; line-height:1.6;">
                Se subió una imagen que nuestro sistema marcó como posible contenido sensible
                en la publicación <strong>"{{ $propiedad->titulo }}"</strong>, de
                <strong>{{ $propiedad->user->name ?? 'un usuario' }}</strong>.
            </p>

            <p style="font-size:16px; line-height:1.6; background:#f3f4f6; padding:14px 18px; border-radius:10px;">
                {{ $motivo }}
            </p>

            <p style="font-size:16px; line-height:1.6;">
                Entra al panel de administración, en <strong>Revisión de contenido</strong>,
                para aprobarla o banear la publicación.
            </p>
        </div>

        <div style="background:#f3f4f6; padding:18px; text-align:center; color:#6b7280; font-size:13px;">
            © {{ date('Y') }} RentaFácil. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
