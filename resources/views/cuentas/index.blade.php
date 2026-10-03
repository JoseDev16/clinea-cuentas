@extends('layouts.base')
@section('titulo', 'Cuentas')
@push('head')
<style>
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:18px 0}
.stat{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;text-decoration:none;color:inherit}
.stat b{display:block;font-size:24px}
.stat small{color:var(--muted)}
.stat.sel{border-color:var(--teal);box-shadow:0 0 0 2px var(--teal-soft)}
.filtros{display:flex;gap:10px;margin-bottom:14px}
.filtros input{flex:1}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:#FBFAF7}
tr:last-child td{border-bottom:none}
td a.fila{color:var(--ink);text-decoration:none;font-weight:700}
@media (max-width:760px){
  .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
  table,thead,tbody,tr,td{display:block}
  thead{display:none}
  tr{border-bottom:1px solid var(--line)}
  td{border:none;padding:6px 14px}
  td:first-child{padding-top:14px}
  td:last-child{padding-bottom:14px}
}
</style>
@endpush
@section('cuerpo')
@include('cuentas._top')
<main class="wrap" style="padding-bottom:48px">
  <h1>Suscripciones</h1>
  <p class="muted">Ingreso mensual de las que están pagando: <b>${{ number_format((float) $mensual, 2) }}</b>
    @if ($porActivar) · <b style="color:var(--bad)">{{ $porActivar }} por activar</b> (pagaron y falta dejar lista su clínica) @endif
  </p>

  <div class="stats">
    @foreach (['activa' => 'Al día', 'atrasada' => 'Atrasadas', 'pendiente' => 'Pendientes de pago', 'cancelada' => 'Canceladas'] as $k => $t)
      <a class="stat {{ $estado === $k ? 'sel' : '' }}" href="{{ route('cuentas.index', $estado === $k ? [] : ['estado' => $k]) }}">
        <b>{{ $conteos[$k] ?? 0 }}</b><small>{{ $t }}</small>
      </a>
    @endforeach
  </div>

  <form class="filtros" method="GET">
    @if ($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
    <input name="q" value="{{ $buscar }}" placeholder="Buscar clínica, contacto o correo">
    <button class="btn">Buscar</button>
  </form>

  @if ($suscripciones->isEmpty())
    <div class="card muted">Todavía no hay suscripciones{{ $estado || $buscar ? ' con ese filtro' : '' }}. Cuando alguien toque «Contratar» en clinea.app aparecerá aquí.</div>
  @else
    <table>
      <thead><tr><th>Clínica</th><th>Plan</th><th>Estado</th><th>Pagos</th><th>Contratada</th></tr></thead>
      <tbody>
      @foreach ($suscripciones as $s)
        <tr>
          <td><a class="fila" href="{{ route('cuentas.show', $s) }}">{{ $s->clinica }}</a><br><small class="muted">{{ $s->nombre_contacto }} · {{ $s->email }}</small></td>
          <td>{{ $s->nombrePlan() }}<br><small class="muted">{{ $s->nombrePais() }} · ${{ number_format((float) $s->monto, 2) }}/mes · día {{ $s->dia_cobro }}</small></td>
          <td>
            <span class="badge b-{{ $s->estado }}">{{ $s->etiquetaEstado() }}</span>
            @if (in_array($s->estado, ['activa', 'atrasada']) && ! $s->instancia_lista_at)<br><span class="badge b-instancia" style="margin-top:4px">Por activar</span>@endif
          </td>
          <td>{{ $s->pagos_realizados }}@if ($s->ultimo_pago_at)<br><small class="muted">último {{ $s->ultimo_pago_at->format('d/m/Y') }}</small>@endif</td>
          <td>{{ $s->created_at->format('d/m/Y') }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <div style="margin-top:14px">{{ $suscripciones->links('cuentas._paginas') }}</div>
  @endif
</main>
@endsection
