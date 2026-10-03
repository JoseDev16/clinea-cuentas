<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Bienvenida a {{ $marca }} · {{ $s->clinica }}</title>
<style>
  @font-face { font-family: 'Poppins'; font-weight: 400; src: url("{{ resource_path('marca/Poppins-Regular.ttf') }}") format('truetype'); }
  @font-face { font-family: 'Poppins'; font-weight: 500; src: url("{{ resource_path('marca/Poppins-Medium.ttf') }}") format('truetype'); }
  @font-face { font-family: 'Poppins'; font-weight: 600; src: url("{{ resource_path('marca/Poppins-SemiBold.ttf') }}") format('truetype'); }
  @font-face { font-family: 'Poppins'; font-weight: 700; src: url("{{ resource_path('marca/Poppins-Bold.ttf') }}") format('truetype'); }

  @page { margin: 0; }
  * { margin: 0; padding: 0; }
  body { font-family: 'Poppins', sans-serif; font-size: 10.5pt; line-height: 0.98; color: #3A434C; background: #FFFFFF; }

  .franja { height: 8px; background: #1F7A8C; }
  .franja-ambar { height: 3px; background: #E8A23D; width: 120px; }

  .cabecera { padding: 26px 56px 22px; background: #E1F1F2; }
  .cabecera table { width: 100%; border-collapse: collapse; }
  .marca { font-size: 21pt; font-weight: 700; color: #23282E; letter-spacing: -0.3px; }
  .marca span { color: #1F7A8C; }
  .lema { font-size: 8.5pt; color: #155E6B; font-weight: 500; }
  .fecha { text-align: right; font-size: 9pt; color: #5C6670; vertical-align: bottom; }

  .cuerpo { padding: 30px 56px 0; }
  .etiqueta { font-size: 8pt; font-weight: 700; letter-spacing: 1.4px; text-transform: uppercase; color: #1F7A8C; margin-bottom: 6px; }
  h1 { font-size: 22pt; line-height: 0.88; font-weight: 700; color: #23282E; margin-bottom: 18px; }
  h1 span { color: #1F7A8C; }
  p { margin-bottom: 10px; }
  b { color: #23282E; font-weight: 600; }

  .columnas { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 8px; }
  .caja { background: #F7F4EE; border-radius: 12px; padding: 16px 18px; vertical-align: top; }
  .caja h2 { font-size: 10.5pt; font-weight: 700; color: #23282E; margin-bottom: 8px; }
  .dato { font-size: 8.5pt; color: #5C6670; line-height: 1.1; }
  .valor { font-size: 10pt; font-weight: 600; color: #23282E; margin-bottom: 7px; line-height: 1.15; }

  .paso { width: 100%; border-collapse: collapse; margin-bottom: 7px; }
  .paso td { vertical-align: top; font-size: 9.5pt; line-height: 0.98; }
  .num { width: 22px; font-size: 13pt; font-weight: 700; color: #E8A23D; line-height: 1; }

  .soporte { margin-top: 18px; border: 1.5px solid #1F7A8C; border-radius: 12px; padding: 14px 18px; }
  .soporte table { width: 100%; border-collapse: collapse; }
  .soporte .grande { font-size: 14pt; font-weight: 700; color: #155E6B; white-space: nowrap; line-height: 1; }

  .firma { margin-top: 22px; }
  .firma .saludo { margin-bottom: 2px; }
  .firma .quien { font-size: 15pt; font-weight: 700; color: #155E6B; line-height: 1; }
  .firma .cargo { font-size: 9pt; color: #5C6670; }

  .pie { position: absolute; bottom: 0; left: 0; right: 0; }
  .pie .texto { padding: 12px 56px 14px; font-size: 8pt; color: #8A9299; border-top: 1px solid #E8E4DA; }
  .pie table { width: 100%; border-collapse: collapse; }
</style>
</head>
<body>
  <div class="franja"></div>

  <div class="cabecera">
    <table>
      <tr>
        <td style="width:56px;vertical-align:middle"><img src="{{ resource_path('marca/clinea-logo.png') }}" width="46" height="46" alt=""></td>
        <td style="vertical-align:middle">
          <div class="marca">Clin<span>ea</span></div>
          <div class="lema">Tu expediente clínico, en línea</div>
        </td>
        <td class="fecha">San Miguel, El Salvador<br>{{ $fecha }}</td>
      </tr>
    </table>
  </div>

  <div class="cuerpo">
    <div class="etiqueta">Carta de bienvenida</div>
    <h1>{{ $nombre }}, te damos la bienvenida a <span>{{ $marca }}</span></h1>

    <p>Nos alegra muchísimo que <b>{{ $s->clinica }}</b> se sume a {{ $marca }}. Desde hoy formas parte de una red de médicos y clínicas en Latinoamérica que decidieron dejar el papel atrás para tener sus expedientes ordenados, seguros y a la mano desde donde estén.</p>

    <p>{{ $marca }} nació en El Salvador con una idea sencilla: que la tecnología trabaje para el médico, y no al revés. Por eso cada cliente tiene detrás a un equipo de personas reales que lo acompaña, desde el primer día y todas las veces que haga falta.</p>

    <table class="columnas">
      <tr>
        <td class="caja" style="width:42%">
          <h2>Tu suscripción</h2>
          <div class="dato">Clínica</div><div class="valor">{{ $s->clinica }}</div>
          <div class="dato">Plan</div><div class="valor">{{ $plan }}</div>
          <div class="dato">Cobro mensual</div><div class="valor">{{ $monto }}, el día {{ $s->dia_cobro }} de cada mes</div>
          @if ($notaMoneda)<div class="dato" style="color:#155E6B;font-weight:600;margin-top:2px">Nunca pagarás más de lo anunciado: por la conversión de tu banco podrías pagar un poco menos.</div>@endif
        </td>
        <td style="width:4%"></td>
        <td class="caja" style="width:54%">
          <h2>Lo que sigue</h2>
          <table class="paso"><tr><td class="num">1</td><td><b>Preparamos tu clínica</b> en {{ $marca }} con tu nombre y tus datos.</td></tr></table>
          <table class="paso"><tr><td class="num">2</td><td><b>En menos de {{ $horas }} horas</b> te enviamos por correo y WhatsApp el enlace para entrar.</td></tr></table>
          <table class="paso"><tr><td class="num">3</td><td><b>Un técnico te escribe</b> por WhatsApp y te acompaña en tu primer ingreso.</td></tr></table>
        </td>
      </tr>
    </table>

    <div class="soporte">
      <table>
        <tr>
          <td style="vertical-align:middle">
            <div class="etiqueta" style="margin-bottom:2px">Soporte {{ $marca }}</div>
            <div style="font-size:9.5pt">Para cualquier duda, escríbenos. Te contesta una persona del equipo.</div>
          </td>
          <td style="text-align:right;vertical-align:middle">
            <div style="font-size:8.5pt;color:#5C6670">WhatsApp</div><div class="grande">{{ $whatsappVisible }}</div>
            <div style="font-size:9.5pt;color:#1F7A8C">{{ $correo }}</div>
          </td>
        </tr>
      </table>
    </div>

    <div class="firma">
      <p class="saludo">Gracias por confiarnos tu consulta. Estamos contentos de empezar este camino contigo.</p>
      <p class="saludo" style="margin-top:12px">Con cariño,</p>
      <div class="quien">El equipo de {{ $marca }}</div>
      <div class="cargo">FSTUDIOS · creadores de {{ $marca }}</div>
      <div class="franja-ambar" style="margin-top:10px"></div>
    </div>
  </div>

  <div class="pie">
    <div class="texto">
      <table>
        <tr>
          <td><b style="color:#5C6670">{{ $marca }}</b> · por FSTUDIOS · {{ $direccion }}</td>
          <td style="text-align:right">clinea.app</td>
        </tr>
      </table>
    </div>
    <div class="franja"></div>
  </div>
</body>
</html>
