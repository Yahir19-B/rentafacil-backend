<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $suspendido ? 'Cuenta suspendida' : 'Infracción registrada' }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f5; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

        <div style="background:{{ $suspendido ? '#b91c1c' : '#166534' }}; padding:30px; text-align:center;">
            @if (!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="RentaFácil" style="width:120px; height:auto; margin-bottom:15px;">
            @endif

            <h1 style="color:#ffffff; margin:0; font-size:26px;">
                {{ $suspendido ? 'Tu cuenta fue suspendida' : 'Se registró una infracción' }}
            </h1>
        </div>

        <div style="padding:35px; color:#1f2937;">
            <h2 style="margin-top:0;">
                Hola {{ $user->name }}
            </h2>

            @if ($suspendido)
                <p style="font-size:16px; line-height:1.6;">
                    Tu cuenta de RentaFácil acumuló <strong>{{ $strikes }} de {{ $limite }} strikes</strong>
                    por incumplir el reglamento de la plataforma, así que fue suspendida durante
                    <strong>{{ $diasSuspension }} días</strong>. Pasado ese tiempo, tu cuenta se
                    reactivará automáticamente.
                </p>

                <p style="font-size:16px; line-height:1.6;">
                    Motivo del último strike: {{ $motivo }}
                </p>

                <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                    Si crees que esto es un error, contacta a nuestro equipo de soporte desde la app.
                </p>
            @else
                <p style="font-size:16px; line-height:1.6;">
                    Detectamos contenido que no cumple con el reglamento de RentaFácil en tu cuenta:
                </p>

                <p style="font-size:16px; line-height:1.6; background:#f3f4f6; padding:14px 18px; border-radius:10px;">
                    {{ $motivo }}
                </p>

                <p style="font-size:16px; line-height:1.6;">
                    Llevas <strong>{{ $strikes }} de {{ $limite }} strikes</strong>. Si llegas al límite,
                    tu cuenta se suspenderá temporalmente.
                </p>
            @endif
        </div>

        <div style="background:#f3f4f6; padding:18px; text-align:center; color:#6b7280; font-size:13px;">
            © {{ date('Y') }} RentaFácil. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
