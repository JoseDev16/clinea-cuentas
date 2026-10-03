@extends('layouts.base')
@section('titulo', 'Demos')
@push('head')
<style>
.filtros{display:flex;gap:10px;margin:18px 0 14px}
.filtros input{flex:1}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:top}
th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);background:#FBFAF7}
tr:last-child td{border-bottom:none}
.b-enviada{background:#EAF6EE;color:#1F6B43}
.b-error{background:#FDEDE6;color:#9A3412}
.b-cuenta_existente{background:#FDF2E1;color:#8A5A12}
@media (max-width:760px){
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
  <h1>Demos</h1>
  <p class="muted">Doctores que pidieron «Prueba Clinea 24 horas» en clinea.app. Hoy: <b>{{ $hoy }}</b> · con acceso vigente: <b>{{ $vigentes }}</b>.</p>

  <form class="filtros" method="GET">
    <input name="q" value="{{ $buscar }}" placeholder="Buscar nombre, correo o especialidad">
    <button class="btn">Buscar</button>
  </form>

  @if ($demos->isEmpty())
    <div class="card muted">Todavía no hay solicitudes{{ $buscar ? ' con esa búsqueda' : '' }}. Cuando alguien pida la demo en clinea.app aparecerá aquí.</div>
  @else
    <table>
      <thead><tr><th>Doctor</th><th>Especialidad</th><th>Estado</th><th>Pidió</th><th></th></tr></thead>
      <tbody>
      @foreach ($demos as $d)
        <tr>
          <td><b>{{ $d->nombre }}</b><br><small class="muted">{{ $d->email }}{{ $d->pais ? ' · '.$d->pais : '' }}</small></td>
          <td>{{ $d->especialidad }}</td>
          <td>
            <span class="badge b-{{ $d->estado }}">{{ $d->etiquetaEstado() }}</span>
            @if ($d->demo_expira_at)<br><small class="muted">{{ $d->vigente() ? 'vence '.$d->demo_expira_at->diffForHumans() : 'venció '.$d->demo_expira_at->format('d/m H:i') }}</small>@endif
            @if ($d->detalle && $d->estado !== 'enviada')<br><small class="muted">{{ $d->detalle }}</small>@endif
          </td>
          <td>{{ $d->created_at->format('d/m/Y H:i') }}</td>
          <td><a class="btn" href="mailto:{{ $d->email }}?subject={{ rawurlencode('Tu demo de Clinea') }}">Escribirle</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <div style="margin-top:14px">{{ $demos->links('cuentas._paginas') }}</div>
  @endif
</main>
@endsection
