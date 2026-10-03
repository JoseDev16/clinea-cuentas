<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('titulo', 'Clinea') · Clinea</title>
<link rel="icon" href="https://clinea.app/assets/favicon.svg" type="image/svg+xml">
<style>
:root{--teal:#1F7A8C;--teal-deep:#155E6B;--teal-soft:#E1F1F2;--paper:#F7F4EE;--amber:#E8A23D;--ink:#23282E;--muted:#5C6670;--line:rgba(35,40,46,.1);--ok:#2E9E62;--bad:#C2410C}
*{box-sizing:border-box;margin:0;padding:0}
body{font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--ink);background:var(--paper)}
a{color:var(--teal)}
.wrap{max-width:1100px;margin:0 auto;padding:0 16px}
.top{background:#fff;border-bottom:1px solid var(--line)}
.top .wrap{display:flex;align-items:center;justify-content:space-between;height:60px;gap:12px}
.logo{display:flex;align-items:baseline;gap:0;font-weight:800;font-size:19px;color:var(--ink);text-decoration:none}
.logo span:not(.muted){color:var(--teal)}
h1{font-size:24px;margin:28px 0 6px}
h2{font-size:17px;margin:0 0 12px}
.muted{color:var(--muted)}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:9px 18px;border-radius:12px;border:1px solid var(--line);background:#fff;color:var(--ink);font:600 14px system-ui,sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{border-color:var(--teal)}
.btn-primary{background:var(--teal);border-color:var(--teal);color:#fff}
.btn-primary:hover{background:var(--teal-deep)}
.btn-danger{color:var(--bad);border-color:rgba(194,65,12,.35)}
.flash{margin:16px 0;padding:12px 16px;border-radius:12px;font-weight:500}
.flash.ok{background:#EAF6EE;color:#1F6B43}
.flash.error{background:#FDEDE6;color:#9A3412}
label{display:block;font-weight:600;font-size:13px;margin:12px 0 5px}
input,textarea,select{width:100%;min-height:44px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;font:inherit;background:#fff}
input:focus,textarea:focus,select:focus{outline:2px solid var(--teal-soft);border-color:var(--teal)}
.badge{display:inline-block;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap}
.b-pendiente{background:#F1EFE8;color:#5F5E5A}
.b-suscrita{background:#E1F1F2;color:#155E6B}
.b-activa{background:#EAF6EE;color:#1F6B43}
.b-atrasada{background:#FDEDE6;color:#9A3412}
.b-cancelada{background:#EEE;color:#666}
.b-instancia{background:#FDF2E1;color:#8A5A12}
.err{color:var(--bad);font-size:13px;margin-top:4px}
</style>
@stack('head')
</head>
<body>
@yield('cuerpo')
</body>
</html>
