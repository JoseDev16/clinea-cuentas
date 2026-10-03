@extends('layouts.base')
@section('titulo', $tipo === 'enviada' ? 'Revisa tu correo' : 'Prueba Clinea')
@php
  $wa = 'https://wa.me/'.config('clinea.whatsapp_soporte').'?text='.rawurlencode('Hola, quiero probar la demo de Clinea'.($email ? ' con el correo '.$email : '').'.');
@endphp
@section('cuerpo')
<main class="wrap" style="max-width:560px;padding-top:56px;padding-bottom:56px">
  <a class="logo" href="https://clinea.app/">Clin<span>ea</span></a>
  <div class="card" style="margin-top:24px">
    @if ($tipo === 'enviada')
      <p style="font-size:44px;line-height:1;margin-bottom:10px" aria-hidden="true">📬</p>
      <h1 style="margin-top:0">¡Listo! Revisa tu correo</h1>
      <p style="margin:10px 0 6px">Te enviamos tu usuario y contraseña a <b>{{ $email }}</b>. Tu acceso dura <b>{{ config('clinea.demo.horas') }} horas</b> desde ahora.</p>
      <p class="muted" style="margin:0 0 20px">Si no lo ves en un par de minutos, busca en «Spam» o «Promociones».</p>
      <a class="btn btn-primary" href="https://clinea.app/">Volver a clinea.app</a>
      <a class="btn" target="_blank" rel="noopener" href="{{ $wa }}" style="margin-left:8px">¿No te llegó? Escríbenos</a>
    @elseif ($tipo === 'error')
      <h1 style="margin-top:0">Revisa tus datos</h1>
      <ul style="margin:12px 0 20px 18px">
        @foreach ($errores as $e)<li>{{ $e }}</li>@endforeach
      </ul>
      <a class="btn btn-primary" href="https://clinea.app/#demo">Volver a intentarlo</a>
    @elseif ($tipo === 'limite')
      <h1 style="margin-top:0">Ya te enviamos tu acceso</h1>
      <p class="muted" style="margin:10px 0 20px">Hoy ya pedimos la demo para <b>{{ $email }}</b>; revisa ese correo (también en «Spam»). Si no te llegó, escríbenos y te ayudamos.</p>
      <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ $wa }}">Escribir por WhatsApp</a>
    @elseif ($tipo === 'cuenta_existente')
      <h1 style="margin-top:0">Ese correo ya tiene una cuenta</h1>
      <p class="muted" style="margin:10px 0 20px">Usa otro correo para la demo, o escríbenos y te ayudamos a entrar.</p>
      <a class="btn btn-primary" href="https://clinea.app/#demo">Usar otro correo</a>
      <a class="btn" target="_blank" rel="noopener" href="{{ $wa }}" style="margin-left:8px">Escribir por WhatsApp</a>
    @else
      <h1 style="margin-top:0">No pudimos preparar tu demo</h1>
      <p class="muted" style="margin:10px 0 20px">Tus datos quedaron guardados y ya nos llegó el aviso: te escribimos pronto. Si prefieres, escríbenos tú por WhatsApp.</p>
      <a class="btn btn-primary" target="_blank" rel="noopener" href="{{ $wa }}">Escribir por WhatsApp</a>
    @endif
  </div>
</main>
@endsection
