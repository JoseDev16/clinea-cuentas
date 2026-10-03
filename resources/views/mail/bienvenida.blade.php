<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Te damos la bienvenida a {{ $marca }}</title>
<style>
  @media (max-width:620px){
    .contenedor{width:100% !important}
    .pad{padding-left:22px !important;padding-right:22px !important}
    .titulo{font-size:25px !important;line-height:32px !important}
    .resumen td{display:block !important;width:auto !important;padding:3px 20px !important}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#F7F4EE;-webkit-text-size-adjust:100%">
@php
  $f = "font-family:'Plus Jakarta Sans','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
  // Incrustado en el correo (no depende de una URL externa); al previsualizar no hay $message.
  $logo = resource_path('marca/clinea-logo.png');
  $logo = isset($message) ? $message->embed($logo) : 'data:image/png;base64,'.base64_encode(file_get_contents($logo));
@endphp
{{-- Texto de vista previa en la bandeja de entrada --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0">Recibimos tu suscripción. Antes de las {{ $promesaHora }} tendrás el enlace para entrar a {{ $marca }} y un técnico te escribirá por WhatsApp.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F7F4EE">
<tr><td align="center" style="padding:28px 12px 40px">

  <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px">

    {{-- Marca --}}
    <tr><td align="left" style="padding:0 8px 18px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="padding-right:10px"><img src="{{ $logo }}" width="40" height="40" alt="" style="display:block;border:0;border-radius:10px"></td>
        <td style="{{ $f }};font-size:22px;font-weight:800;color:#23282E;letter-spacing:-.3px">Clin<span style="color:#1F7A8C">ea</span></td>
      </tr></table>
    </td></tr>

    {{-- Tarjeta principal --}}
    <tr><td style="background:#FFFFFF;border-radius:20px;border:1px solid #E8E4DA;overflow:hidden">

      {{-- Franja de bienvenida --}}
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="height:6px;line-height:6px;font-size:0;background:#1F7A8C">&nbsp;</td></tr>
        <tr><td class="pad" style="padding:34px 40px 8px;background:#E1F1F2">
          <p style="margin:0 0 10px;{{ $f }};font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#155E6B">Suscripción recibida</p>
          <h1 class="titulo" style="margin:0 0 12px;{{ $f }};font-size:29px;line-height:36px;font-weight:800;color:#23282E">¡Hola, {{ $nombre }}! Te damos la bienvenida a {{ $marca }}.</h1>
          <p style="margin:0 0 28px;{{ $f }};font-size:16px;line-height:25px;color:#3A434C">Recibimos tu suscripción para <b>{{ $s->clinica }}</b> y nuestro equipo ya está preparando tu sistema. Gracias por confiar en nosotros.</p>
        </td></tr>
      </table>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

        {{-- La promesa: lo que va a pasar y cuándo --}}
        <tr><td class="pad" style="padding:30px 40px 6px">
          <p style="margin:0 0 16px;{{ $f }};font-size:18px;font-weight:800;color:#23282E">Esto es lo que sigue</p>

          @foreach ([
            ['1', 'Preparamos tu clínica', 'Creamos tu espacio en '.$marca.' con el nombre de '.$s->clinica.', listo para tus pacientes.'],
            ['2', 'Te enviamos tu acceso', 'A más tardar a las '.$promesaHora.' ('.$promesaDia.') recibirás en este correo y por WhatsApp el enlace para entrar.'],
            ['3', 'Un técnico te acompaña', 'Alguien de nuestro equipo te escribirá por WhatsApp para ayudarte en tu primer ingreso y resolver tus dudas.'],
          ] as [$n, $titulo, $texto])
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px">
            <tr>
              <td width="44" valign="top">
                <div style="width:32px;height:32px;line-height:32px;border-radius:16px;background:#1F7A8C;color:#FFFFFF;text-align:center;{{ $f }};font-size:15px;font-weight:800">{{ $n }}</div>
              </td>
              <td valign="top" style="{{ $f }};font-size:15px;line-height:23px;color:#3A434C">
                <b style="color:#23282E;font-size:16px">{{ $titulo }}</b><br>{{ $texto }}
              </td>
            </tr>
          </table>
          @endforeach
        </td></tr>

        {{-- Resumen de la suscripción --}}
        <tr><td class="pad" style="padding:8px 40px 4px">
          <table role="presentation" class="resumen" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F7F4EE;border-radius:14px">
            <tr><td style="padding:18px 20px 4px;{{ $f }};font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5C6670" colspan="2">Tu suscripción</td></tr>
            <tr>
              <td style="padding:6px 20px;{{ $f }};font-size:15px;color:#5C6670" width="40%">Clínica</td>
              <td style="padding:6px 20px;{{ $f }};font-size:15px;font-weight:700;color:#23282E">{{ $s->clinica }}</td>
            </tr>
            <tr>
              <td style="padding:6px 20px;{{ $f }};font-size:15px;color:#5C6670">Plan</td>
              <td style="padding:6px 20px;{{ $f }};font-size:15px;font-weight:700;color:#23282E">{{ $plan }}</td>
            </tr>
            <tr>
              <td style="padding:6px 20px 18px;{{ $f }};font-size:15px;color:#5C6670">Cobro</td>
              <td style="padding:6px 20px 18px;{{ $f }};font-size:15px;font-weight:700;color:#23282E">{{ $monto }} al mes, el día {{ $s->dia_cobro }}</td>
            </tr>
          </table>
          <p style="margin:12px 2px 0;{{ $f }};font-size:13px;line-height:20px;color:#5C6670">En tu estado de cuenta el cobro aparece a nombre del representante legal de {{ $marca }}, que es quien administra los pagos. Puedes cancelar cuando quieras escribiéndonos.</p>
        </td></tr>

        {{-- Soporte --}}
        <tr><td class="pad" style="padding:28px 40px 8px">
          <p style="margin:0 0 6px;{{ $f }};font-size:18px;font-weight:800;color:#23282E">¿Alguna duda? Estamos a un mensaje</p>
          <p style="margin:0 0 18px;{{ $f }};font-size:15px;line-height:23px;color:#3A434C">Escríbenos por WhatsApp al <b>{{ $whatsappVisible }}</b> o responde a este correo. Te contesta una persona del equipo.</p>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
            <td style="border-radius:12px;background:#1F7A8C">
              <a href="{{ $whatsappUrl }}" target="_blank" style="display:inline-block;padding:14px 26px;{{ $f }};font-size:16px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:12px">Escribir por WhatsApp</a>
            </td>
          </tr></table>
        </td></tr>

        {{-- Cierre cálido --}}
        <tr><td class="pad" style="padding:26px 40px 34px">
          <p style="margin:0 0 14px;{{ $f }};font-size:15px;line-height:23px;color:#3A434C">Te adjuntamos una carta de bienvenida. Desde hoy formas parte de la red de médicos y clínicas de Latinoamérica que trabajan con {{ $marca }}, y nos alegra mucho tenerte aquí.</p>
          <p style="margin:0;{{ $f }};font-size:15px;line-height:23px;color:#3A434C">Con cariño,</p>
          <p style="margin:2px 0 0;{{ $f }};font-size:17px;font-weight:800;color:#155E6B">El equipo de {{ $marca }}</p>
          <div style="width:44px;height:3px;background:#E8A23D;border-radius:2px;margin-top:10px;line-height:3px;font-size:0">&nbsp;</div>
        </td></tr>
      </table>
    </td></tr>

    {{-- Pie --}}
    <tr><td align="center" style="padding:22px 16px 0;{{ $f }};font-size:12px;line-height:19px;color:#8A9299">
      <b style="color:#5C6670">{{ $marca }}</b> · por FSTUDIOS<br>
      {{ $direccion }}<br>
      <a href="https://clinea.app" style="color:#1F7A8C;text-decoration:none">clinea.app</a> · <a href="mailto:{{ $correo }}" style="color:#1F7A8C;text-decoration:none">{{ $correo }}</a> · {{ $whatsappVisible }}<br>
      Recibes este correo porque contrataste {{ $marca }} para {{ $s->clinica }}.
    </td></tr>
  </table>

</td></tr>
</table>
</body>
</html>
