¡Hola, {{ $nombre }}! Te damos la bienvenida a {{ $marca }}.

Recibimos {{ $recibimos }} para {{ $s->clinica }} y nuestro equipo ya está preparando tu sistema. Gracias por confiar en nosotros.

ESTO ES LO QUE SIGUE
1. Preparamos tu clínica: creamos tu espacio en {{ $marca }} con el nombre de {{ $s->clinica }}.
2. Te enviamos tu acceso: a más tardar a las {{ $promesaHora }} ({{ $promesaDia }}) recibirás en este correo y por WhatsApp el enlace para entrar.
3. Un técnico te acompaña: alguien de nuestro equipo te escribirá por WhatsApp para ayudarte en tu primer ingreso.

{{ mb_strtoupper($tituloResumen) }}
Clínica: {{ $s->clinica }}
Plan: {{ $plan }}
@if ($unico)
Pago: {{ $cobro }}
@else
Cobro: {{ $monto }} al mes, el día {{ $s->dia_cobro }}
@endif
@if ($notaMoneda && ! $unico)
{{ $notaMoneda }}
@endif
@if ($unico)
En tu estado de cuenta el cobro aparece a nombre del representante legal de {{ $marca }}. Es un pago de un mes: no se te cobrará nada más de forma automática; antes de que termine te escribimos para renovar.
@else
En tu estado de cuenta el cobro aparece a nombre del representante legal de {{ $marca }}. Puedes cancelar cuando quieras escribiéndonos.
@endif

¿Alguna duda? Escríbenos por WhatsApp al {{ $whatsappVisible }} ({{ $whatsappUrl }}) o responde a este correo.

Te adjuntamos una carta de bienvenida. Nos alegra mucho tenerte aquí.

Con cariño,
El equipo de {{ $marca }}

{{ $marca }} · por FSTUDIOS · {{ $direccion }} · clinea.app
