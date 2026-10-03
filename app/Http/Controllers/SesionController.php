<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SesionController
{
    public function create()
    {
        return view('cuentas.login');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Sin «recordarme»: es el panel de pagos, la sesión vence a las 2 horas.
        if (! Auth::attempt($datos)) {
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('cuentas.index'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
