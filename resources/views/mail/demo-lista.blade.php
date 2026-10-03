<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Tu demo de {{ $marca }} está lista</title>
<style>
  @media (max-width:620px){
    .contenedor{width:100% !important}
    .pad{padding-left:22px !important;padding-right:22px !important}
    .titulo{font-size:25px !important;line-height:32px !important}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#F7F4EE;-webkit-text-size-adjust:100%">
@php
  $f = "font-family:'Plus Jakarta Sans','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
  $mono = "font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace";
  $logo = resource_path('marca/clinea-logo.png');
  $logo = isset($message) ? $message->embed($logo) : 'data:image/png;base64,'.base64_encode(file_get_contents($logo));
@endphp
<div style="display:none;max-height:0;overflow:hidden;opacity:0">Tu usuario y contraseña para probar {{ $marca }} durante {{ $horas }} horas.</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F7F4EE">
<tr><td align="center" style="padding:28px 12px 40px">
  <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px">

    <tr><td align="left" style="padding:0 8px 18px">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="padding-right:10px"><img src="{{ $logo }}" width="40" height="40" alt="" style="display:block;border:0;border-radius:10px"></td>
        <td style="{{ $f }};font-size:22px;font-weight:800;color:#23282E;letter-spacing:-.3px">Clin<span style="color:#1F7A8C">ea</span></td>
      </tr></table>
    </td></tr>

    <tr><td style="background:#FFFFFF;border-radius:20px;border:1px solid #E8E4DA;overflow:hidden">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="height:6px;line-height:6px;font-size:0;background:#1F7A8C">&nbsp;</td></tr>
        <tr><td class="pad" style="padding:34px 40px 26px;background:#E1F1F2">
          <p style="margin:0 0 10px;{{ $f }};font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#155E6B">Tu demo está lista</p>
          <h1 class="titulo" style="margin:0 0 12px;{{ $f }};font-size:29px;line-height:36px;font-weight:800;color:#23282E">¡Hola, {{ $nombre }}! Ya puedes probar {{ $marca }}.</h1>
          <p style="margin:0;{{ $f }};font-size:16px;line-height:25px;color:#3A434C">Te preparamos un acceso de <b>{{ $horas }} horas</b> para que conozcas el sistema por tu cuenta, a tu ritmo.</p>
        </td></tr>

        {{-- Credenciales --}}
        <tr><td class="pad" style="padding:30px 40px 6px">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:2px solid #1F7A8C;border-radius:16px">
            <tr><td style="padding:20px 22px 6px;{{ $f }};font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#155E6B">Tus datos de acceso</td></tr>
            <tr><td style="padding:6px 22px 2px;{{ $f }};font-size:13px;color:#5C6670">Correo</td></tr>
            <tr><td style="padding:0 22px 10px;{{ $mono }};font-size:17px;font-weight:700;color:#23282E">{{ $correo }}</td></tr>
            <tr><td style="padding:6px 22px 2px;{{ $f }};font-size:13px;color:#5C6670">Contraseña</td></tr>
            <tr><td style="padding:0 22px 14px"><span style="display:inline-block;{{ $mono }};font-size:20px;font-weight:700;letter-spacing:1px;color:#23282E;background:#F7F4EE;border-radius:8px;padding:6px 12px">{{ $password }}</span></td></tr>
            <tr><td style="padding:0 22px 20px;{{ $f }};font-size:14px;color:#9A3412"><b>Vence el {{ $vence }}</b></td></tr>
          </table>

          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px"><tr>
            <td style="border-radius:12px;background:#1F7A8C">
              <a href="{{ $url }}" target="_blank" style="display:inline-block;padding:15px 30px;{{ $f }};font-size:17px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:12px">Entrar a la demo →</a>
            </td>
          </tr></table>
        </td></tr>

        {{-- Consejos --}}
        <tr><td class="pad" style="padding:28px 40px 4px">
          <p style="margin:0 0 14px;{{ $f }};font-size:18px;font-weight:800;color:#23282E">Para aprovecharla</p>
          @foreach ([
            ['Te guiamos paso a paso', 'Al entrar se abre un recorrido que te enseña a agendar, atender una consulta, hacer la receta en PDF y cobrar, haciéndolo de verdad.'],
            ['Úsala como en tu consultorio', 'Entras como doctor: crea pacientes, atiende consultas y prueba todo lo que quieras.'],
            ['Solo datos de prueba', 'Es una clínica de demostración que comparten otros doctores: no escribas datos reales de tus pacientes.'],
          ] as [$titulo, $texto])
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px">
            <tr>
              <td width="22" valign="top" style="{{ $f }};font-size:18px;font-weight:800;color:#E8A23D;line-height:22px">•</td>
              <td valign="top" style="{{ $f }};font-size:15px;line-height:23px;color:#3A434C"><b style="color:#23282E">{{ $titulo }}.</b> {{ $texto }}</td>
            </tr>
          </table>
          @endforeach
        </td></tr>

        {{-- Soporte y siguiente paso --}}
        <tr><td class="pad" style="padding:18px 40px 34px">
          <p style="margin:0 0 16px;{{ $f }};font-size:15px;line-height:23px;color:#3A434C">¿Dudas mientras pruebas? Escríbenos por WhatsApp al <b>{{ $whatsappVisible }}</b> o responde a este correo: te contesta una persona del equipo.</p>
          <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
            <td style="border-radius:12px;border:1.5px solid #1F7A8C">
              <a href="{{ $whatsappUrl }}" target="_blank" style="display:inline-block;padding:12px 22px;{{ $f }};font-size:15px;font-weight:700;color:#1F7A8C;text-decoration:none">Escribir por WhatsApp</a>
            </td>
          </tr></table>
          <p style="margin:22px 0 0;{{ $f }};font-size:15px;line-height:23px;color:#3A434C">¿Te convenció? Elige tu plan en <a href="https://clinea.app/#planes" style="color:#1F7A8C;font-weight:700">clinea.app</a> y en menos de 3 horas tienes tu propia clínica lista.</p>
          <p style="margin:18px 0 0;{{ $f }};font-size:15px;color:#3A434C">Con cariño,</p>
          <p style="margin:2px 0 0;{{ $f }};font-size:17px;font-weight:800;color:#155E6B">El equipo de {{ $marca }}</p>
        </td></tr>
      </table>
    </td></tr>

    <tr><td align="center" style="padding:22px 16px 0;{{ $f }};font-size:12px;line-height:19px;color:#8A9299">
      <b style="color:#5C6670">{{ $marca }}</b> · por FSTUDIOS<br>
      {{ $direccion }}<br>
      <a href="https://clinea.app" style="color:#1F7A8C;text-decoration:none">clinea.app</a> · <a href="mailto:{{ $correoSoporte }}" style="color:#1F7A8C;text-decoration:none">{{ $correoSoporte }}</a> · {{ $whatsappVisible }}<br>
      Recibes este correo porque pediste probar {{ $marca }} en clinea.app.
    </td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
