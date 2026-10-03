<x-mail::message>
# {{ $asunto }}

@foreach ($bloques as $b)
**{{ $b['titulo'] }}**

@foreach ($b['lineas'] as $l)
- {{ $l }}
@endforeach

@isset($b['url'])
[Ver en el panel]({{ $b['url'] }})
@endisset

@endforeach

<x-mail::button :url="url('/cuentas')">
Abrir el panel de cuentas
</x-mail::button>
</x-mail::message>
