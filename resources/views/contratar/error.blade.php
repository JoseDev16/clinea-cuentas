@extends('layouts.base')
@section('titulo', 'No pudimos continuar')
@section('cuerpo')
<main class="wrap" style="max-width:560px;padding-top:56px;padding-bottom:56px">
  <a class="logo" href="https://clinea.app/">Clin<span>ea</span></a>
  <div class="card" style="margin-top:24px">
    @isset($errores)
      <h1 style="margin-top:0">Revisa tus datos</h1>
      <ul style="margin:12px 0 20px 18px">
        @foreach ($errores as $e)<li>{{ $e }}</li>@endforeach
      </ul>
      <a class="btn btn-primary" href="https://clinea.app/#planes">Volver a los planes</a>
    @else
      <h1 style="margin-top:0">No pudimos abrir el pago</h1>
      <p class="muted" style="margin:10px 0 20px">Tus datos quedaron guardados, pero la página de pago no respondió. Escríbenos y te mandamos el enlace en unos minutos.</p>
      <a class="btn btn-primary" target="_blank" rel="noopener"
         href="https://wa.me/{{ config('clinea.whatsapp_soporte') }}?text={{ rawurlencode('Hola, quise contratar Clinea para '.($s->clinica ?? 'mi clínica').' y no abrió el pago.') }}">Escribir por WhatsApp</a>
      <a class="btn" href="https://clinea.app/#planes" style="margin-left:8px">Volver</a>
    @endisset
  </div>
</main>
@endsection
