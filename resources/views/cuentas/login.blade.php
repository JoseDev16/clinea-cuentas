@extends('layouts.base')
@section('titulo', 'Entrar')
@section('cuerpo')
<main class="wrap" style="max-width:420px;padding-top:72px">
  <div class="logo">Clín<span>ea</span> <span class="muted" style="font-weight:600;font-size:15px;margin-left:8px">· cuentas</span></div>
  <form class="card" method="POST" action="{{ url('/cuentas/entrar') }}" style="margin-top:20px">
    @csrf
    <label for="email">Correo</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    @error('email')<p class="err">{{ $message }}</p>@enderror
    <label for="password">Contraseña</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <button class="btn btn-primary" style="width:100%;margin-top:18px">Entrar</button>
  </form>
</main>
@endsection
