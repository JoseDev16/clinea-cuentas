<?php

namespace Tests\Feature;

use App\Mail\Aviso;
use App\Mail\Bienvenida;
use App\Models\PagoWompi;
use App\Models\Suscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Honduras y Guatemala: enlace fijo de Wompi, un pago de un mes, webhook + revisión de respaldo. */
class PagoUnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.wompi.app_id' => 'app-test',
            'services.wompi.api_secret' => 'secret-test',
            'clinea.avisos_a' => ['hello@fstudios.dev'],
            'clinea.avisos_pago_a' => ['josefbarillas@gmail.com'],
        ]);
        Mail::fake();
    }

    private function contratar(string $pais = 'HN', array $extra = [])
    {
        return $this->withHeader('Origin', 'https://clinea.app')
            ->withServerVariables(['GEOIP_COUNTRY' => $pais])
            ->post('/contratar', array_merge([
                'plan' => 'whatsapp', 'pais' => $pais,
                'nombre' => 'Dr. Luis Paz', 'clinica' => 'Clínica Tegucigalpa',
                'email' => 'Luis@Correo.com', 'whatsapp' => '+504 9000 0000',
            ], $extra));
    }

    private function webhook(array $cambios = [], ?string $firma = null)
    {
        $cuerpo = json_encode(array_replace_recursive([
            'IdCuenta' => 'cta',
            'FechaTransaccion' => now()->toIso8601String(),
            'Monto' => 13,
            'ModuloUtilizado' => 'EnlacePago',
            'FormaPagoUtilizada' => 'PagoNormal',
            'IdTransaccion' => 'tx-1',
            'ResultadoTransaccion' => 'ExitosaAprobada',
            'EsProductiva' => true,
            'EnlacePago' => ['Id' => 4598413, 'IdentificadorEnlaceComercio' => 'clinea-hn', 'NombreProducto' => 'Contratacion Clinea Honduras'],
            'cliente' => ['Nombre' => 'Luis Paz', 'Email' => 'luis@correo.com'],
        ], $cambios));

        return $this->call('POST', '/cuentas/wompi/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WOMPI_HASH' => $firma ?? hash_hmac('sha256', $cuerpo, 'secret-test'),
        ], $cuerpo);
    }

    public function test_contratar_en_honduras_guarda_la_ficha_avisa_y_manda_al_enlace(): void
    {
        $this->contratar('HN')->assertRedirect('https://s.wompi.sv/2434832wB7');

        $s = Suscripcion::sole();
        $this->assertSame(['unico', 'pendiente', 'luis@correo.com'], [$s->tipo, $s->estado, $s->email]);
        Mail::assertSent(Aviso::class, fn ($m) => str_contains($m->asunto, 'fue a pagar'));
        Mail::assertNotSent(Bienvenida::class);
    }

    public function test_con_ip_de_el_salvador_no_usa_el_enlace_de_honduras(): void
    {
        Http::fake([
            'id.wompi.sv/*' => Http::response(['access_token' => 'tok']),
            'api.wompi.sv/EnlacePagoRecurrente' => Http::response(['idEnlace' => 'e1', 'urlEnlace' => 'https://lk.wompi.sv/x']),
        ]);
        $this->contratar('SV', ['pais' => 'HN']);

        $this->assertSame(['SV', 'recurrente'], [Suscripcion::sole()->pais, Suscripcion::sole()->tipo]);
    }

    public function test_el_webhook_con_firma_invalida_se_rechaza(): void
    {
        $this->contratar('HN');
        $this->webhook([], 'firma-falsa')->assertStatus(401);
        $this->webhook([], '')->assertStatus(401);

        $this->assertSame(0, PagoWompi::count());
        $this->assertSame('pendiente', Suscripcion::sole()->estado);
    }

    public function test_el_webhook_registra_el_pago_avisa_a_los_dos_correos_y_manda_la_bienvenida(): void
    {
        $this->contratar('HN');
        Mail::fake();

        $this->webhook()->assertOk()->assertJson(['registrado' => true]);

        $s = Suscripcion::sole();
        $this->assertSame(['suscrita', 1], [$s->estado, $s->pagos_realizados]);
        $p = PagoWompi::sole();
        $this->assertTrue($p->coincidio_por_correo);
        $this->assertSame([$s->id, 'webhook'], [$p->suscripcion_id, $p->origen]);

        Mail::assertSent(Aviso::class, fn ($m) => str_contains($m->asunto, 'PAGO RECIBIDO')
            && $m->hasTo('hello@fstudios.dev') && $m->hasTo('josefbarillas@gmail.com'));
        Mail::assertSent(Bienvenida::class, fn ($m) => $m->hasTo('luis@correo.com'));
    }

    public function test_el_mismo_pago_dos_veces_se_registra_una_sola(): void
    {
        $this->contratar('HN');
        $this->webhook()->assertOk();
        $this->webhook()->assertOk()->assertJson(['registrado' => false]);

        $this->assertSame(1, PagoWompi::count());
        $this->assertSame(1, Suscripcion::sole()->pagos_realizados);
    }

    public function test_pagos_rechazados_o_de_otros_enlaces_no_cuentan(): void
    {
        $this->webhook(['ResultadoTransaccion' => 'ExitosaDeclinada'])->assertOk()->assertJson(['registrado' => false]);
        $this->webhook(['IdTransaccion' => 'tx-2', 'EnlacePago' => ['Id' => 999]])->assertOk()->assertJson(['registrado' => false]);

        $this->assertSame(0, PagoWompi::count());
    }

    public function test_si_pago_sin_llenar_el_formulario_igual_te_avisa_y_le_da_la_bienvenida(): void
    {
        $this->webhook(['EnlacePago' => ['Id' => 4598430], 'cliente' => ['Nombre' => 'Rosa Pérez', 'Email' => 'rosa@correo.com']])->assertOk();

        $s = Suscripcion::sole();
        $this->assertSame(['GT', 'unico', 'suscrita', 'rosa@correo.com'], [$s->pais, $s->tipo, $s->estado, $s->email]);
        Mail::assertSent(Aviso::class, fn ($m) => str_contains($m->asunto, 'PAGO RECIBIDO'));
        Mail::assertSent(Bienvenida::class, fn ($m) => $m->hasTo('rosa@correo.com'));
    }

    public function test_la_bienvenida_de_pago_unico_habla_de_un_mes_y_no_de_suscripcion(): void
    {
        $this->contratar('HN');
        $this->webhook();

        $html = (new Bienvenida(Suscripcion::sole()))->render();
        $this->assertStringContainsString('L370 por 1 mes (pagado: US$13.00)', $html);
        $this->assertStringContainsString('Recibimos tu pago', $html);
        $this->assertStringNotContainsString('al mes, el día', $html);
    }

    public function test_la_revision_de_respaldo_detecta_el_pago_si_el_webhook_no_llego(): void
    {
        $this->contratar('HN');
        Mail::fake();
        Http::fake([
            'id.wompi.sv/*' => Http::response(['access_token' => 'tok']),
            'api.wompi.sv/EnlacePago/4598413' => Http::response(['transacciones' => [
                ['idTransaccion' => 'tx-9', 'monto' => 13, 'esReal' => true, 'esAprobada' => true, 'fechaTransaccion' => now()->subMinutes(10)->toIso8601String()],
                // Muy reciente: se espera al webhook.
                ['idTransaccion' => 'tx-10', 'monto' => 13, 'esReal' => true, 'esAprobada' => true, 'fechaTransaccion' => now()->toIso8601String()],
                ['idTransaccion' => 'tx-11', 'monto' => 13, 'esReal' => true, 'esAprobada' => false, 'fechaTransaccion' => now()->subHour()->toIso8601String()],
            ]]),
            'api.wompi.sv/EnlacePago/4598430' => Http::response(['transacciones' => []]),
        ]);

        $this->artisan('cuentas:pagos-unicos')->assertSuccessful();
        $this->artisan('cuentas:pagos-unicos')->assertSuccessful();

        $this->assertSame(['tx-9'], PagoWompi::pluck('id_transaccion')->all());
        $this->assertSame('suscrita', Suscripcion::sole()->estado);
        $this->assertSame('revision', PagoWompi::sole()->origen);
        Mail::assertSent(Aviso::class, 1);
        Mail::assertSent(Bienvenida::class, 1);

        // Si luego llega el webhook de ese mismo pago, no se duplica.
        $this->webhook(['IdTransaccion' => 'tx-9'])->assertOk()->assertJson(['registrado' => false]);
        $this->assertSame(1, PagoWompi::count());
    }
}
