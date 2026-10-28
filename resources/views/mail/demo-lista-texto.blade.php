@if ($reenvio)
¡Hola, {{ $nombre }}! {{ $marca }} ya tiene odontograma.

Hace unos días te enviamos una demo de {{ $marca }}. Ahora preparamos una versión especial para odontólogos: odontograma, presupuestos que salen de lo que marcas en la boca y tratamientos con prima y cuotas. Te creamos un acceso nuevo de {{ $duracion }} y te adjuntamos una guía en PDF.
@elseif ($dental)
¡Hola, {{ $nombre }}! Ya puedes probar {{ $marca }} para odontólogos.

Te preparamos un acceso de {{ $duracion }} con odontograma, presupuestos y tratamientos por cuotas. Te adjuntamos una guía en PDF.
@else
¡Hola, {{ $nombre }}! Ya puedes probar {{ $marca }}.

Te preparamos un acceso de {{ $duracion }} para que conozcas el sistema por tu cuenta.
@endif

TUS DATOS DE ACCESO
Entrar en: {{ $url }}
Correo: {{ $correo }}
Contraseña: {{ $password }}
Vence el {{ $vence }}

PARA APROVECHARLA
@if ($dental)
- Ya tienes cuatro pacientes de ejemplo: rehabilitación por cuotas, ortodoncia, un niño con dentición mixta y un presupuesto por aceptar.
- La guía adjunta explica el odontograma, los presupuestos y las cuotas con capturas reales.
- Es una clínica de demostración compartida: no escribas datos reales de tus pacientes.
@else
- Al entrar se abre un recorrido que te guía paso a paso.
- Entras como doctor: crea pacientes, atiende consultas y prueba todo.
- Es una clínica de demostración compartida: no escribas datos reales de tus pacientes.
@endif

¿Dudas? WhatsApp {{ $whatsappVisible }} ({{ $whatsappUrl }}) o responde a este correo.
¿Te convenció? Elige tu plan en https://clinea.app/#planes

El equipo de {{ $marca }}
{{ $marca }} · por FSTUDIOS · {{ $direccion }}
