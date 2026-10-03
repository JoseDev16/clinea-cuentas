<header class="top">
  <div class="wrap">
    <a class="logo" href="{{ route('cuentas.index') }}">Clin<span>ea</span> <span class="muted" style="font-weight:600;font-size:15px;margin-left:8px">· cuentas</span></a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn">Salir</button></form>
  </div>
</header>
<div class="wrap">
  @if (session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
  @if (session('error'))<div class="flash error">{{ session('error') }}</div>@endif
</div>
