<?php

namespace Tests\Feature;

use App\Mail\Aviso;
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
        config(['services.wompi.app_id' => 'app-test', 'services.wompi.api_secret' => 'secret-test']);
        Mail::fake();
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
        $this->assertSame('14.00', $s->monto);
        $this->assertSame(17, $s->dia_cobro);
        $this->assertSame('ana@correo.com', $s->email);
        $this->assertSame('enl-123', $s->wompi_enlace_id);

        Http::assertSent(fn ($r) => $r->url() === 'https://api.wompi.sv/EnlacePagoRecurrente'
            && $r['monto'] == 14.0
            && $r['diaDePago'] === 17
            && $r['idAplicativo'] === 'app-test'
            && str_contains($r['nombre'], 'Clínica San Rafael'));
        Mail::assertSent(Aviso::class);
    }

    public function test_la_descripcion_explica_el_nombre_del_comercio_y_los_pasos(): void
    {
        config(['clinea.comercio_wompi' => 'TITULAR DE PRUEBA']);
        $this->fakeWompi();

        $this->post('/contratar', $this->datos());

        Http::assertSent(fn ($r) => $r->url() === 'https://api.wompi.sv/EnlacePagoRecurrente'
            && str_contains($r['descripcionProducto'], 'el comercio aparece como TITULAR DE PRUEBA')
            && str_contains($r['descripcionProducto'], 'elige «Sí»')
            && str_contains($r['descripcionProducto'], '$14.00 al mes'));
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
        $this->assertSame('9.00', Suscripcion::sole()->monto);
    }

    public function test_honduras_usa_su_precio(): void
    {
        $this->fakeWompi();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/contratar', $this->datos(['plan' => 'expediente']));

        $this->assertSame('HN', Suscripcion::sole()->pais);
        $this->assertSame('9.99', Suscripcion::sole()->monto);
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
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 1, 'estado' => 1, 'nombreSuscriptor' => 'ANA LOPEZ', 'monto' => 14]]);
        $this->post('/contratar', $this->datos());

        $this->artisan('cuentas:revisar')->expectsOutputToContain('primer_pago')->assertSuccessful();

        $s = Suscripcion::sole();
        $this->assertSame('activa', $s->estado);
        $this->assertSame(1, $s->pagos_realizados);
        $this->assertSame('ANA LOPEZ', $s->wompi_nombre_suscriptor);
        $this->assertCount(1, $s->pagos);

        // Mes siguiente: Wompi ya lleva 2 pagos.
        Carbon::setTestNow('2026-11-17 12:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 2, 'estado' => 1, 'nombreSuscriptor' => 'ANA LOPEZ', 'monto' => 14]]);
        $this->artisan('cuentas:revisar')->expectsOutputToContain('pago')->assertSuccessful();

        $this->assertSame(2, $s->fresh()->pagos_realizados);
        $this->assertSame('activa', $s->fresh()->estado);
    }

    public function test_marca_atrasada_si_pasa_el_dia_de_cobro_sin_pago(): void
    {
        Carbon::setTestNow('2026-10-17 10:00');
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 1, 'estado' => 1, 'monto' => 14]]);
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
        $this->fakeWompi([['id' => 'sus-1', 'pagosRealizados' => 2, 'estado' => 1, 'monto' => 14]]);
        $this->artisan('cuentas:revisar');
        $this->assertSame('activa', Suscripcion::sole()->estado);
    }

    public function test_el_panel_pide_login(): void
    {
        $this->get('/cuentas')->assertRedirect('/cuentas/entrar');
    }
}
