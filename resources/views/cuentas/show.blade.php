@extends('layouts.base')
@section('titulo', $s->clinica)
@push('head')
<style>
.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px;margin-top:16px}
dl{display:grid;grid-template-columns:150px 1fr;gap:8px 14px}
dt{color:var(--muted);font-size:14px}
dd{font-weight:500;overflow-wrap:anywhere}
.acciones{display:flex;flex-direction:column;gap:10px}
.acciones form{display:contents}
.pagos{list-style:none}
.pagos li{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)}
.pagos li:last-child{border-bottom:none}
@media (max-width:760px){.grid{grid-template-columns:1fr}dl{grid-template-columns:1fr;gap:2px}dd{margin-bottom:8px}}
</style>
@endpush
@section('cuerpo')
@include('cuentas._top')
<main class="wrap" style="padding-bottom:48px">
  <p style="margin-top:20px"><a href="{{ route('cuentas.index') }}">← Todas las suscripciones</a></p>
  <h1>{{ $s->clinica }}</h1>
  <p>
    <span class="badge b-{{ $s->estado }}">{{ $s->etiquetaEstado() }}</span>
    @if ($s->instancia_lista_at)
      <span class="badge b-activa">Clínica activada {{ $s->instancia_lista_at->format('d/m/Y') }}</span>
    @elseif (in_array($s->estado, ['activa', 'atrasada']))
      <span class="badge b-instancia">Pagó: falta activar su clínica</span>
    @endif
  </p>

  <div class="grid">
    <div class="card">
      <h2>Datos</h2>
      <dl>
        <dt>Contacto</dt><dd>{{ $s->nombre_contacto }}</dd>
        <dt>Correo</dt><dd><a href="mailto:{{ $s->email }}">{{ $s->email }}</a></dd>
        <dt>WhatsApp</dt><dd><a href="{{ $s->enlaceWhatsApp() }}" target="_blank" rel="noopener">{{ $s->whatsapp }}</a></dd>
        <dt>Plan</dt><dd>{{ $s->nombrePlan() }} · {{ $s->nombrePais() }}</dd>
        <dt>Monto</dt><dd>${{ number_format((float) $s->monto, 2) }} al mes, el día {{ $s->dia_cobro }}</dd>
        <dt>Solicitud</dt><dd>{{ $s->created_at->format('d/m/Y H:i') }}</dd>
        <dt>Primer pago</dt><dd>{{ $s->primer_pago_at?->format('d/m/Y') ?? '—' }}</dd>
        <dt>Último pago</dt><dd>{{ $s->ultimo_pago_at?->format('d/m/Y') ?? '—' }}</dd>
        <dt>Enlace Wompi</dt><dd>@if ($s->wompi_url)<a href="{{ $s->wompi_url }}" target="_blank" rel="noopener">{{ $s->wompi_url }}</a>@else — @endif</dd>
        <dt>En Wompi</dt><dd>{{ $s->wompi_nombre_suscriptor ?? 'Sin suscriptor todavía' }}@if (! is_null($s->wompi_estado)) <span class="muted">(estado {{ $s->wompi_estado }})</span>@endif</dd>
        <dt>Revisado</dt><dd>{{ $s->revisada_at?->diffForHumans() ?? 'Nunca' }}</dd>
      </dl>
    </div>

    <div class="acciones">
      <div class="card acciones">
        <h2>Acciones</h2>
        <form method="POST" action="{{ route('cuentas.revisar', $s) }}">@csrf<button class="btn btn-primary">Revisar pagos en Wompi ahora</button></form>
        <form method="POST" action="{{ route('cuentas.instancia', $s) }}">@csrf
          <button class="btn">{{ $s->instancia_lista_at ? 'Quitar marca de clínica activada' : 'Marcar clínica activada' }}</button>
        </form>
        @if ($s->wompi_url)
          <a class="btn" target="_blank" rel="noopener"
             href="{{ $s->enlaceWhatsApp() }}?text={{ rawurlencode('Hola '.$s->nombre_contacto.', este es tu enlace para suscribirte a Clínea: '.$s->wompi_url) }}">Reenviarle el enlace de pago</a>
        @endif
        @if ($s->estado !== 'cancelada')
          <form method="POST" action="{{ route('cuentas.cancelar', $s) }}" onsubmit="return confirm('¿Cancelar la suscripción de {{ addslashes($s->clinica) }}? Se desactiva su enlace en Wompi y no se le cobra más.')">@csrf
            <button class="btn btn-danger">Cancelar suscripción</button>
          </form>
        @endif
      </div>

      <div class="card">
        <h2>Pagos detectados</h2>
        @if ($s->pagos->isEmpty())
          <p class="muted">Ninguno todavía.</p>
        @else
          <ul class="pagos">
            @foreach ($s->pagos as $p)
              <li><span>Pago #{{ $p->numero }}</span><span>${{ number_format((float) $p->monto, 2) }} · {{ $p->detectado_at->format('d/m/Y') }}</span></li>
            @endforeach
          </ul>
        @endif
      </div>

      <form class="card" method="POST" action="{{ route('cuentas.notas', $s) }}">@csrf
        <h2>Notas</h2>
        <textarea name="notas" rows="4" placeholder="Seguimiento, datos de su instancia…">{{ old('notas', $s->notas) }}</textarea>
        <button class="btn" style="margin-top:10px">Guardar notas</button>
      </form>
    </div>
  </div>
</main>
@endsection
