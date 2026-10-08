<?php

namespace Tests\Feature;

use App\Mail\Aviso;
use App\Mail\Bienvenida;
use App\Models\User;
use App\Models\Suscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContratarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Estas pruebas cubren el flujo de Wompi: el pago en línea va encendido.
        config(['services.wompi.app_id' => 'app-test', 'services.wompi.api_secret' => 'secret-test', 'clinea.pagos.online_habilitado' => true]);
        Mail::fake();
        // Como lo manda el navegador desde la landing.
        $this->withHeader('Origin', 'https://clinea.app');
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'plan' => 'whatsapp',
            'pais' => 'SV',
            'nombre' => 'Dra. Ana López',
            'clinica' => 'Clínica San Rafael',
            'email' => 'Ana@Correo.com',
            'whatsapp' => '+503 7000 0000',
        ], $extra);
    }

    /** Lo que Wompi responde al listar suscripciones; las pruebas lo cambian entre meses. */
    private array $subs = [];

    private bool $fakeado = false;

    private function fakeWompi(array $suscripciones = []): void
    {
        $this->subs = $suscripciones;
        if ($this->fakeado) {
            return;
        }
        $this->fakeado = true;
        Http::fake([
            'id.wompi.sv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'api.wompi.sv/EnlacePagoRecurrente' => Http::response([
                'idEnlace' => 'enl-123', 'urlEnlace' => 'https://lk.wompi.sv/abc', 'estaProductivo' => false,
            ]),
            // Solo el estado 1 trae la suscripción; los demás vienen vacíos.
            'api.wompi.sv/EnlacePagoRecurrente/enl-123/suscripciones*' => function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);

                return Http::response(($q['Estado'] ?? null) == 1 ? $this->subs : []);
            },
        ]);
    }

    public function test_crea_el_enlace_de_la_clinica_y_redirige_a_wompi(): void
    {
        Carbon::setTestNow('2026-10-17 10:00');
        $this->fakeWompi();

        $this->post('/contratar', $this->datos())
            ->assertRedirect('https://lk.wompi.sv/abc');

        $s = Suscripcion::sole();
        $this->assertSame('pendiente', $s->estado);
        $this->assertSame('SV', $s->pais);
        $this->assertSame('20.00', $s->monto);
        $this->assertSame(17, $s->dia_cobro);
        $this->assertSame('ana@correo.com', $s->email);
        $this->assertSame('enl-123', $s->wompi_enlace_id);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.wompi.sv/EnlacePagoRecurrente'
            && $r['monto'] == 20.0
            && $r['diaDePago'] === 17
            && $r['idAplicativo'] === 'app-test'
            && str_contains($r['nombre'], 'Clínica San Rafael'));
        Mail::assertSent(Aviso::class);
    }

    public function test_la_descripcion_explica_el_nombre_del_comercio_y_los_pasos(): void
    {
        $this->fakeWompi();

        $this->post('/contratar', $this->datos());

        Http::assertSent(fn ($r) => $r->url() === 'https://api.wompi.sv/EnlacePagoRecurrente'
            && str_contains($r['descripcionProducto'], 'el nombre del representante legal de Clinea')
            && str_contains($r['descripcionProducto'], 'elige «Sí»')
            && str_contains($r['descripcionProducto'], 'En «Alias» escribe el nombre de tu clínica: Clínica San Rafael')
            && str_contains($r['descripcionProducto'], '$20.00 al mes'));
    }

    public function test_el_dia_de_cobro_no_pasa_del_28(): void
    {
        Carbon::setTestNow('2026-10-31 10:00');
        $this->fakeWompi();

        $this->post('/contratar', $this->datos());

        $this->assertSame(28, Suscripcion::sole()->dia_cobro);
    }

    public function test_el_pais_de_la_ip_manda_sobre_el_de_la_pagina(): void
    {
        $this->fakeWompi();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'SV'])
            ->post('/contratar', $this->datos(['pais' => 'HN', 'plan' => 'expediente']));

        $this->assertSame('SV', Suscripcion::sole()->pais);
        $this->assertSame('10.00', Suscripcion::sole()->monto);
    }

    public function test_fuera_de_el_salvador_y_honduras_paga_el_precio_de_el_salvador_aunque_la_pagina_diga_hn(): void
    {
        $this->fakeWompi();

        // IP de otro país (o VPN) y la página pidió Honduras: igual paga SV.
        $this->withServerVariables(['GEOIP_COUNTRY' => 'US'])
            ->post('/contratar', $this->datos(['pais' => 'HN', 'plan' => 'whatsapp']));

        $this->assertSame('SV', Suscripcion::sole()->pais);
        $this->assertSame('20.00', Suscripcion::sole()->monto);
    }

    public function test_honduras_usa_su_precio(): void
    {
        $this->fakeWompi();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/contratar', $this->datos(['plan' => 'expediente']));

        $this->assertSame('HN', Suscripcion::sole()->pais);
        $this->assertSame('8.00', Suscripcion::sole()->monto);
    }

    public function test_datos_invalidos_muestran_la_pagina_de_error_sin_crear_nada(): void
    {
        $this->fakeWompi();

        $this->post('/contratar', $this->datos(['email' => 'no-es-correo', 'plan' => 'gratis']))
            ->assertStatus(422)
            ->assertSee('Revisa tus datos');

        $this->assertSame(0, Suscripcion::count());
        Http::assertNothingSent();
    }

    public function test_el_campo_trampa_descarta_bots(): void
    {
        $this->fakeWompi();

        $this->post('/contratar', $this->datos(['sitio_web' => 'http://spam.test']))->assertRedirect('https://clinea.app/');

        $this->assertSame(0, Suscripcion::count());
    }

    public function test_si_wompi_falla_guarda_la_solicitud_y_ofrece_whatsapp(): void
    {
        Http::fake(['*' => Http::response(['error' => 'x'], 500)]);

        $this->post('/contratar', $this->datos())
            ->assertStatus(502)
            ->assertSee('No pudimos abrir el pago');

        $s = Suscripcion::sole();
        $this->assertNull($s->wompi_enlace_id);
        $this->assertStringContainsString('No se pudo crear el enlace', $s->notas);
        Mail::assertSent(Aviso::class);
    }

    public function test_revisar_detecta_el_primer_pago_y_los_siguientes(): void
    {
        Carbon::setTestNow('2026-10-17 10:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 1, 'estado' => 'Activa', 'nombreSuscriptor' => 'ANA LOPEZ', 'alias' => 'San Rafael', 'monto' => 14]]);
        $this->post('/contratar', $this->datos());

        $this->artisan('cuentas:revisar')->expectsOutputToContain('primer_pago')->assertSuccessful();

        $s = Suscripcion::sole();
        $this->assertSame('activa', $s->estado);
        $this->assertSame(1, $s->pagos_realizados);
        $this->assertSame('ANA LOPEZ', $s->wompi_nombre_suscriptor);
        $this->assertSame('San Rafael', $s->wompi_alias);
        $this->assertCount(1, $s->pagos);
        $this->assertNotNull($s->suscrita_at);
        Mail::assertSent(Bienvenida::class, 1);

        // Mes siguiente: Wompi ya lleva 2 pagos.
        Carbon::setTestNow('2026-11-17 12:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 2, 'estado' => 'Activa', 'nombreSuscriptor' => 'ANA LOPEZ', 'monto' => 14]]);
        $this->artisan('cuentas:revisar')->expectsOutputToContain('pago')->assertSuccessful();

        $this->assertSame(2, $s->fresh()->pagos_realizados);
        $this->assertSame('activa', $s->fresh()->estado);
    }

    public function test_al_suscribirse_sin_pagos_todavia_queda_suscrita_y_le_llega_la_bienvenida(): void
    {
        // Lo que Wompi responde justo después de suscribirse (y siempre, en modo
        // desarrollo): estado en texto y 0 pagos.
        Carbon::setTestNow('2026-10-03 09:30');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 0, 'estado' => 'Activa', 'nombreSuscriptor' => 'JOSE FLORES', 'alias' => 'clinica san jose', 'monto' => 14]]);
        $this->post('/contratar', $this->datos());

        $this->artisan('cuentas:revisar --recientes')->expectsOutputToContain('suscripcion')->assertSuccessful();

        $s = Suscripcion::sole();
        $this->assertSame('suscrita', $s->estado);
        $this->assertSame('Activa', $s->wompi_estado);
        $this->assertSame('sus-1', $s->wompi_suscripcion_id);
        $this->assertSame('JOSE FLORES', $s->wompi_nombre_suscriptor);
        $this->assertSame('clinica san jose', $s->wompi_alias);
        $this->assertEquals(Carbon::parse('2026-10-03 09:30'), $s->suscrita_at);
        $this->assertEquals(Carbon::parse('2026-10-03 12:30'), $s->instanciaPrometidaPara());
        $this->assertNotNull($s->bienvenida_enviada_at);
        $this->assertCount(0, $s->pagos);

        Mail::assertSent(Bienvenida::class, fn (Bienvenida $m) => $m->hasTo('ana@correo.com')
            && $m->hasReplyTo('hello@fstudios.dev')
            && $m->hasFrom(config('mail.from.address'), 'Clinea'));
        // Y a fstudios el aviso para preparar la instancia.
        Mail::assertSent(Aviso::class, fn (Aviso $m) => str_contains($m->asunto, 'se suscribió'));

        // La siguiente revisión no la vuelve a mandar.
        $this->artisan('cuentas:revisar')->assertSuccessful();
        Mail::assertSent(Bienvenida::class, 1);
    }

    public function test_si_falla_el_correo_la_bienvenida_se_reintenta_en_la_siguiente_revision(): void
    {
        Carbon::setTestNow('2026-10-03 09:30');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 0, 'estado' => 'Activa', 'monto' => 14]]);
        $this->post('/contratar', $this->datos());

        // Correo caído: un SMTP real que no responde.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        Mail::swap(new \Illuminate\Mail\MailManager(app()));
        $this->artisan('cuentas:revisar --recientes --sin-avisos');
        $s = Suscripcion::sole();
        $this->assertSame('suscrita', $s->estado);
        $this->assertNull($s->bienvenida_enviada_at);

        Mail::fake();
        $this->artisan('cuentas:revisar --sin-avisos');
        $this->assertNotNull($s->fresh()->bienvenida_enviada_at);
        Mail::assertSent(Bienvenida::class, 1);
    }

    public function test_la_revision_de_cada_minuto_solo_mira_las_pendientes_recientes(): void
    {
        Carbon::setTestNow('2026-10-01 09:00');
        $this->fakeWompi([]);
        $this->post('/contratar', $this->datos());

        // Tres días después ya no se vigila cada minuto (la de cada hora sí la ve).
        Carbon::setTestNow('2026-10-04 09:00');
        $this->artisan('cuentas:revisar --recientes --sin-avisos');
        $this->assertNull(Suscripcion::sole()->revisada_at);

        $this->artisan('cuentas:revisar --sin-avisos');
        $this->assertNotNull(Suscripcion::sole()->revisada_at);
    }

    public function test_el_correo_de_bienvenida_lleva_la_marca_la_hora_prometida_y_el_soporte(): void
    {
        Carbon::setTestNow('2026-10-03 09:30');
        $s = Suscripcion::create([
            'pais' => 'SV', 'plan' => 'whatsapp', 'monto' => 14, 'dia_cobro' => 3,
            'nombre_contacto' => 'Dra. Ana López', 'clinica' => 'Clínica San Rafael',
            'email' => 'ana@correo.com', 'whatsapp' => '+503 7000 0000', 'suscrita_at' => now(),
        ]);

        $html = (new Bienvenida($s))->render();

        $this->assertStringContainsString('¡Hola, Dra. Ana López! Te damos la bienvenida a Clinea.', $html);
        $this->assertStringContainsString('12:30 p. m.', $html);
        $this->assertStringContainsString('+503 6678-1544', $html);
        $this->assertStringContainsString('Premium', $html);
        $this->assertStringNotContainsString('Clínea', $html);

        $adjuntos = (new Bienvenida($s))->attachments();
        $this->assertCount(1, $adjuntos);
    }

    public function test_guatemala_usa_su_precio(): void
    {
        $this->fakeWompi();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'GT'])->post('/contratar', $this->datos(['plan' => 'whatsapp']));

        $this->assertSame('GT', Suscripcion::sole()->pais);
        $this->assertSame('13.00', Suscripcion::sole()->monto);
        $this->assertSame('Guatemala', Suscripcion::sole()->nombrePais());
    }

    public function test_la_bienvenida_de_guatemala_habla_de_quetzales(): void
    {
        $s = Suscripcion::create([
            'pais' => 'GT', 'plan' => 'expediente', 'monto' => 8, 'dia_cobro' => 3,
            'nombre_contacto' => 'Dra. Rosa Pérez', 'clinica' => 'Clínica Antigua',
            'email' => 'rosa@correo.com', 'whatsapp' => '+502 5000 0000', 'suscrita_at' => now(),
        ]);

        $html = (new Bienvenida($s))->render();
        $this->assertStringContainsString('Q65 (se cobra US$8.00)', $html);
        $this->assertStringContainsString('Nunca pagarás más de Q65 al mes', $html);
        $this->assertStringContainsString('lo convierte a quetzales', $html);
        $this->assertStringNotContainsString('lempiras', $html);
    }

    public function test_la_bienvenida_de_honduras_promete_no_pagar_mas_de_lo_anunciado(): void
    {
        $s = Suscripcion::create([
            'pais' => 'HN', 'plan' => 'whatsapp', 'monto' => 13, 'dia_cobro' => 3,
            'nombre_contacto' => 'Dr. Luis Paz', 'clinica' => 'Clínica Tegucigalpa',
            'email' => 'luis@correo.com', 'whatsapp' => '+504 9000 0000', 'suscrita_at' => now(),
        ]);

        $html = (new Bienvenida($s))->render();
        $this->assertStringContainsString('L370 (se cobra US$13.00)', $html);
        $this->assertStringContainsString('Nunca pagarás más de L370 al mes', $html);
        $this->assertStringContainsString('lo convierte a lempiras', $html);

        // En El Salvador no aparece.
        $s->update(['pais' => 'SV', 'monto' => 14]);
        $this->assertStringNotContainsString('Nunca pagarás', (new Bienvenida($s->fresh()))->render());
    }

    public function test_el_panel_muestra_la_carta_en_pdf_y_reenvia_la_bienvenida(): void
    {
        $s = Suscripcion::create([
            'pais' => 'SV', 'plan' => 'expediente', 'monto' => 9, 'dia_cobro' => 3, 'estado' => 'suscrita',
            'nombre_contacto' => 'Dra. Ana López', 'clinica' => 'Clínica San Rafael',
            'email' => 'ana@correo.com', 'whatsapp' => '+503 7000 0000', 'suscrita_at' => now(),
        ]);
        $admin = User::factory()->create();

        $pdf = $this->actingAs($admin)->get(route('cuentas.bienvenida.pdf', $s))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($admin)->post(route('cuentas.bienvenida', $s))->assertSessionHas('ok');
        Mail::assertSent(Bienvenida::class, 1);
        $this->assertNotNull($s->fresh()->bienvenida_enviada_at);
    }

    public function test_marca_atrasada_si_pasa_el_dia_de_cobro_sin_pago(): void
    {
        Carbon::setTestNow('2026-10-17 10:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 1, 'estado' => 'Activa', 'monto' => 14]]);
        $this->post('/contratar', $this->datos());
        $this->artisan('cuentas:revisar');

        // 18/11: un día después del cobro, todavía dentro de la gracia (3 días).
        Carbon::setTestNow('2026-11-18 10:00');
        $this->artisan('cuentas:revisar');
        $this->assertSame('activa', Suscripcion::sole()->estado);

        // 21/11: ya pasó la gracia y Wompi sigue en 1 pago.
        Carbon::setTestNow('2026-11-21 10:00');
        $this->artisan('cuentas:revisar')->expectsOutputToContain('atraso');
        $this->assertSame('atrasada', Suscripcion::sole()->estado);

        // Llega el pago atrasado: vuelve a estar al día.
        Carbon::setTestNow('2026-11-22 10:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 2, 'estado' => 'Activa', 'monto' => 14]]);
        $this->artisan('cuentas:revisar');
        $this->assertSame('activa', Suscripcion::sole()->estado);
    }

    // ===================== Seguridad =====================

    public function test_sin_origin_ni_referer_se_rechaza(): void
    {
        $this->fakeWompi();
        $this->withoutHeader('Origin')->post('/contratar', $this->datos())->assertForbidden();
        $this->withHeader('Origin', 'https://otro-sitio.com')->post('/contratar', $this->datos())->assertForbidden();
        $this->assertSame(0, Suscripcion::count());
        Http::assertNothingSent();
    }

    public function test_con_referer_de_la_landing_se_acepta(): void
    {
        $this->fakeWompi();
        $this->withoutHeader('Origin')->withHeader('Referer', 'https://clinea.app/#planes')
            ->post('/contratar', $this->datos())
            ->assertRedirect('https://lk.wompi.sv/abc');
    }

    public function test_no_admite_enlaces_en_el_nombre_de_la_clinica(): void
    {
        $this->fakeWompi();
        $this->post('/contratar', $this->datos(['clinica' => 'Paga aquí: www.evil.com']))
            ->assertStatus(422)
            ->assertSee('sin enlaces');
        $this->post('/contratar', $this->datos(['nombre' => 'Visita clinica-falsa.com ya']))->assertStatus(422);
        $this->assertSame(0, Suscripcion::count());
        Http::assertNothingSent();
    }

    public function test_limpia_codigo_y_caracteres_invisibles_antes_de_mandarlo_a_wompi(): void
    {
        $this->fakeWompi();
        $this->post('/contratar', $this->datos([
            'clinica' => "<script>alert(1)</script> Clínica\u{202E}  San\nRafael",
            'nombre' => "Dra. Ana {{7*7}} López",
        ]))->assertRedirect();

        $s = Suscripcion::sole();
        $this->assertSame('script alert(1) script Clínica San Rafael', $s->clinica);
        $this->assertSame('Dra. Ana 7 7 López', $s->nombre_contacto);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.wompi.sv/EnlacePagoRecurrente'
            && ! str_contains($r['nombre'], '<') && ! str_contains($r['descripcionProducto'], '<'));
    }

    public function test_si_vuelve_a_contratar_reusa_su_enlace_en_vez_de_crear_otro(): void
    {
        $this->fakeWompi();
        $this->post('/contratar', $this->datos())->assertRedirect('https://lk.wompi.sv/abc');
        $this->post('/contratar', $this->datos())->assertRedirect('https://lk.wompi.sv/abc');

        $this->assertSame(1, Suscripcion::count());
        Http::assertSentCount(2); // token + un solo enlace
    }

    public function test_maximo_tres_solicitudes_por_correo_al_dia(): void
    {
        $this->fakeWompi();
        foreach (range(1, 3) as $i) {
            Suscripcion::create([
                'pais' => 'SV', 'plan' => 'expediente', 'monto' => 9, 'dia_cobro' => 3, 'estado' => 'cancelada',
                'nombre_contacto' => 'Ana', 'clinica' => "Clínica {$i}", 'email' => 'ana@correo.com', 'whatsapp' => '+503 7000 0000',
            ]);
        }
        $this->post('/contratar', $this->datos())->assertStatus(429)->assertSee('varias solicitudes');
    }

    public function test_limita_las_solicitudes_por_ip(): void
    {
        Http::fake([
            'id.wompi.sv/*' => Http::response(['access_token' => 'tok']),
            'api.wompi.sv/EnlacePagoRecurrente' => fn () => Http::response(['idEnlace' => uniqid('enl-'), 'urlEnlace' => 'https://lk.wompi.sv/abc']),
        ]);
        foreach (range(1, 5) as $i) {
            $this->post('/contratar', $this->datos(['email' => "ana{$i}@correo.com"]))->assertRedirect();
        }
        $this->post('/contratar', $this->datos(['email' => 'ana6@correo.com']))
            ->assertStatus(429)
            ->assertSee('demasiadas solicitudes');
    }

    public function test_nunca_redirige_fuera_de_wompi(): void
    {
        Http::fake([
            'id.wompi.sv/*' => Http::response(['access_token' => 'tok']),
            'api.wompi.sv/*' => Http::response(['idEnlace' => 'enl-9', 'urlEnlace' => 'https://evil.example/pagar']),
        ]);
        $this->post('/contratar', $this->datos())->assertStatus(502);
    }

    public function test_el_panel_lleva_csp_y_no_se_guarda_en_cache(): void
    {
        $admin = User::factory()->create();
        $r = $this->actingAs($admin)->get('/cuentas')->assertOk();

        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'nonce-", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_el_nombre_de_una_clinica_no_ejecuta_codigo_en_el_panel(): void
    {
        $s = Suscripcion::create([
            'pais' => 'SV', 'plan' => 'expediente', 'monto' => 9, 'dia_cobro' => 3,
            'nombre_contacto' => 'x', 'clinica' => "x'); alert(1); ('\"><img src=x onerror=alert(1)>",
            'email' => 'x@correo.com', 'whatsapp' => '+503 7000 0000',
        ]);
        $html = $this->actingAs(User::factory()->create())->get(route('cuentas.show', $s))->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_el_login_se_bloquea_tras_varios_intentos(): void
    {
        User::factory()->create(['email' => 'admin@clinea.app']);
        foreach (range(1, 5) as $i) {
            $this->post('/cuentas/entrar', ['email' => 'admin@clinea.app', 'password' => 'mala'.$i]);
        }
        $this->post('/cuentas/entrar', ['email' => 'admin@clinea.app', 'password' => 'otra'])->assertStatus(429);
    }

    public function test_el_login_no_deja_cookie_de_recordarme(): void
    {
        User::factory()->create(['email' => 'admin@clinea.app', 'password' => 'clave-segura-123']);
        $r = $this->post('/cuentas/entrar', ['email' => 'admin@clinea.app', 'password' => 'clave-segura-123'])
            ->assertRedirect(route('cuentas.index'));

        foreach ($r->headers->getCookies() as $c) {
            $this->assertStringStartsNotWith('remember_web', $c->getName());
        }
    }

    public function test_el_panel_pide_login(): void
    {
        $this->get('/cuentas')->assertRedirect('/cuentas/entrar');
    }
    // ===================== Pago en línea apagado (se contrata por WhatsApp) =====================

    public function test_con_el_pago_en_linea_apagado_contratar_no_crea_nada_y_regresa_a_los_planes(): void
    {
        config(['clinea.pagos.online_habilitado' => false]);
        Http::fake();

        $this->post('/contratar', $this->datos())->assertRedirect('https://clinea.app/#planes');

        $this->assertSame(0, Suscripcion::count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_el_estado_le_dice_a_la_landing_si_hay_pago_en_linea_y_a_que_whatsapp_escribir(): void
    {
        config(['clinea.pagos.online_habilitado' => false]);
        $this->get('/contratar/estado')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJson([
                'pagos_online' => false,
                'whatsapp' => '50366781544',
                'mensajes' => ['contratar' => 'Hola, quiero contratar el plan {plan} de Clinea{pais}.'],
            ]);

        config(['clinea.pagos.online_habilitado' => true]);
        $this->get('/contratar/estado')->assertJson(['pagos_online' => true]);
    }

    public function test_los_planes_se_llaman_igual_en_los_tres_paises_y_solo_cambia_el_precio(): void
    {
        foreach (['SV', 'HN', 'GT'] as $pais) {
            $this->assertSame('Básico', config("clinea.planes.{$pais}.expediente.nombre"));
            $this->assertSame('Premium', config("clinea.planes.{$pais}.whatsapp.nombre"));
        }
        $this->assertSame(10.0, config('clinea.planes.SV.expediente.monto'));
        $this->assertSame(20.0, config('clinea.planes.SV.whatsapp.monto'));
        $this->assertSame(['L240', 'L370'], [config('clinea.planes.HN.expediente.anunciado'), config('clinea.planes.HN.whatsapp.anunciado')]);
        $this->assertSame(['Q65', 'Q105'], [config('clinea.planes.GT.expediente.anunciado'), config('clinea.planes.GT.whatsapp.anunciado')]);
    }
}
