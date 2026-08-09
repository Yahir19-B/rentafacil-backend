<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de reactivación</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f5; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:30px auto; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.08);">

        <div style="background:#166534; padding:30px; text-align:center;">
            @if (!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="RentaFácil" style="width:120px; height:auto; margin-bottom:15px;">
            @endif

            <h1 style="color:#ffffff; margin:0; font-size:26px;">
                Reactiva tu cuenta
            </h1>
        </div>

        <div style="padding:35px; color:#1f2937;">
            <h2 style="margin-top:0;">
                Hola {{ $user->name }}
            </h2>

            <p style="font-size:16px; line-height:1.6;">
                Recibimos una solicitud para reactivar tu cuenta de RentaFácil. Usa el siguiente código
                para confirmarlo:
            </p>

            <div style="text-align:center; margin:35px 0;">
                <span style="display:inline-block; background:#f3f4f6; color:#166534; padding:16px 32px; border-radius:12px; font-size:32px; font-weight:bold; letter-spacing:8px;">
                    {{ $codigo }}
                </span>
            </div>

            <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                Este código vence en {{ $minutosVigencia }} minutos y solo puede utilizarse una vez.
            </p>

            <p style="font-size:14px; color:#6b7280; line-height:1.5;">
                Si tú no solicitaste reactivar tu cuenta, ignora este correo.
            </p>
        </div>

        <div style="background:#f3f4f6; padding:18px; text-align:center; color:#6b7280; font-size:13px;">
            © {{ date('Y') }} RentaFácil. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
