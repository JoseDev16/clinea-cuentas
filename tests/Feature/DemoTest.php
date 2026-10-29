<?php

namespace Tests\Feature;

use App\Mail\Aviso;
use App\Mail\DemoLista;
use App\Models\SolicitudDemo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** «Prueba gratis»: de la landing a la instancia demo y al correo del doctor. */
class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['clinea.demo.url' => 'https://demo.clinea.app', 'clinea.demo.token' => 'tok-demo']);
        Mail::fake();
        $this->withHeader('Origin', 'https://clinea.app');
        Carbon::setTestNow('2026-10-03 10:00');
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Dra. Ana López',
            'especialidad' => 'Pediatría',
            'email' => 'Ana@Correo.com',
            'whatsapp' => '+503 7000 0000',
        ], $extra);
    }

    private function fakeDemo(int $status = 201): void
    {
        Http::fake(['demo.clinea.app/api/accesos-demo' => Http::response($status >= 400 ? ['error' => 'x'] : [
            'url' => 'https://demo.clinea.app/login',
            'correo' => 'ana@correo.com',
            'password' => 'Clave12345',
            'expira_en' => '2026-10-04T10:00:00-06:00',
            'nuevo' => true,
        ], $status)]);
    }

    public function test_crea_el_acceso_y_le_manda_las_credenciales_por_correo(): void
    {
        $this->fakeDemo();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos())
            ->assertOk()
            ->assertSee('Revisa tu correo')
            ->assertSee('ana@correo.com')
            ->assertDontSee('Clave12345'); // la contraseña solo va al correo

        Http::assertSent(fn ($r) => $r->url() === 'https://demo.clinea.app/api/accesos-demo'
            && $r->hasHeader('Authorization', 'Bearer tok-demo')
            && $r['correo'] === 'ana@correo.com'
            && $r['especialidad'] === 'Pediatría'
            && $r['pais'] === 'HN');

        $d = SolicitudDemo::sole();
        $this->assertSame('enviada', $d->estado);
        $this->assertSame('+503 7000 0000', $d->whatsapp);
        $this->assertNotNull($d->demo_expira_at);

        Mail::assertSent(DemoLista::class, fn (DemoLista $m) => $m->hasTo('ana@correo.com')
            && $m->hasReplyTo('hello@fstudios.dev')
            && str_contains($m->render(), 'Clave12345')
            && str_contains($m->render(), 'https://demo.clinea.app/login')
            && str_contains($m->envelope()->subject, '7 días')
            && str_contains($m->render(), 'acceso de <b>7 días</b>'));
        Mail::assertSent(Aviso::class, fn (Aviso $m) => str_contains($m->asunto, 'nueva demo')
            && str_contains($m->render(), '+503 7000 0000'));
    }

    public function test_el_whatsapp_es_obligatorio_y_debe_ser_un_numero(): void
    {
        $this->fakeDemo();

        $this->post('/demo', $this->datos(['whatsapp' => '']))->assertStatus(422);
        $this->post('/demo', $this->datos(['whatsapp' => 'llamame']))->assertStatus(422)->assertSee('WhatsApp válido');

        $this->assertSame(0, SolicitudDemo::count());
        Http::assertNothingSent();
    }

    public function test_si_el_correo_es_de_una_cuenta_real_no_se_envia_nada(): void
    {
        $this->fakeDemo(409);

        $this->post('/demo', $this->datos())->assertStatus(409)->assertSee('ya tiene una cuenta');

        $this->assertSame('cuenta_existente', SolicitudDemo::sole()->estado);
        Mail::assertNotSent(DemoLista::class);
    }

    public function test_si_la_demo_no_responde_guarda_el_lead_y_avisa(): void
    {
        $this->fakeDemo(500);

        $this->post('/demo', $this->datos())->assertStatus(502)->assertSee('No pudimos preparar tu demo');

        $this->assertSame('error', SolicitudDemo::sole()->estado);
        Mail::assertNotSent(DemoLista::class);
        Mail::assertSent(Aviso::class, fn (Aviso $m) => str_contains($m->asunto, 'no se pudo entregar'));
    }

    public function test_maximo_dos_solicitudes_por_correo_al_dia(): void
    {
        $this->fakeDemo();
        $this->post('/demo', $this->datos())->assertOk();
        $this->post('/demo', $this->datos())->assertOk();

        $this->post('/demo', $this->datos())->assertStatus(429)->assertSee('Ya te enviamos tu acceso');
        $this->assertSame(2, SolicitudDemo::count());
        Http::assertSentCount(2);
    }

    public function test_valida_y_limpia_los_datos(): void
    {
        $this->fakeDemo();

        $this->post('/demo', $this->datos(['especialidad' => 'Hacker']))->assertStatus(422)->assertSee('Elige tu especialidad');
        $this->post('/demo', $this->datos(['nombre' => 'Visita www.evil.com']))->assertStatus(422);
        $this->post('/demo', $this->datos(['email' => 'no-es-correo']))->assertStatus(422);

        $this->assertSame(0, SolicitudDemo::count());
        Http::assertNothingSent();
    }

    public function test_sin_origin_de_la_landing_se_rechaza(): void
    {
        $this->fakeDemo();
        $this->withoutHeader('Origin')->post('/demo', $this->datos())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_el_campo_trampa_no_crea_nada(): void
    {
        $this->fakeDemo();
        $this->post('/demo', $this->datos(['sitio_web' => 'http://spam']))->assertRedirect('https://clinea.app/');
        Http::assertNothingSent();
    }

    public function test_limita_las_solicitudes_por_ip(): void
    {
        $this->fakeDemo();
        foreach (range(1, 5) as $i) {
            $this->post('/demo', $this->datos(['email' => "doc{$i}@correo.com"]))->assertOk();
        }
        $this->post('/demo', $this->datos(['email' => 'doc6@correo.com']))->assertStatus(429)->assertSee('demasiadas solicitudes');
    }

    public function test_el_panel_lista_las_demos(): void
    {
        Http::fake(['demo.clinea.app/api/accesos-demo/actividad*' => Http::response(['demos' => []])]);
        SolicitudDemo::create(['nombre' => 'Dra. Ana López', 'especialidad' => 'Pediatría', 'email' => 'ana@correo.com', 'whatsapp' => '+503 7000 0000', 'estado' => 'enviada', 'demo_expira_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create())->get('/cuentas/demos')
            ->assertOk()
            ->assertSee('Dra. Ana López')
            ->assertSee('Pediatría')
            ->assertSee('Acceso enviado')
            ->assertSee('https://wa.me/50370000000', false);
        $this->get('/cuentas/demos')->assertOk(); // ya con sesión
        auth()->logout();
        $this->get('/cuentas/demos')->assertRedirect('/cuentas/entrar');
    }

    public function test_el_panel_dice_quien_entro_a_su_demo_y_quien_no(): void
    {
        $base = ['especialidad' => 'Pediatría', 'estado' => 'enviada', 'demo_expira_at' => now()->addDay()];
        SolicitudDemo::create($base + ['nombre' => 'Dra. Ana López', 'email' => 'ana@correo.com', 'whatsapp' => '+504 9000 0000']);
        SolicitudDemo::create($base + ['nombre' => 'Dr. Luis Paz', 'email' => 'luis@correo.com']);
        SolicitudDemo::create(['nombre' => 'Vieja', 'especialidad' => 'Pediatría', 'email' => 'vieja@correo.com', 'estado' => 'error']);

        Http::fake(['demo.clinea.app/api/accesos-demo/actividad*' => Http::response(['demos' => [
            ['correo' => 'ana@correo.com', 'primer_ingreso_en' => '2026-10-03T08:00:00-06:00', 'ultimo_ingreso_en' => '2026-10-03T09:00:00-06:00', 'ingresos' => 3, 'expira_en' => '2026-10-10T08:00:00-06:00'],
            ['correo' => 'luis@correo.com', 'primer_ingreso_en' => null, 'ultimo_ingreso_en' => null, 'ingresos' => 0, 'expira_en' => '2026-10-10T08:00:00-06:00'],
        ]])]);

        $this->actingAs(User::factory()->create())->get('/cuentas/demos')
            ->assertOk()
            ->assertSee('✓ Entró 3 veces')
            ->assertSee('✗ No ha entrado')
            ->assertSee('1 de 2');

        // Solo se pregunta por las que sí recibieron su acceso, con el token.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/accesos-demo/actividad')
            && $r->hasHeader('Authorization', 'Bearer tok-demo')
            && str_contains(urldecode($r->url()), 'ana@correo.com')
            && ! str_contains(urldecode($r->url()), 'vieja@correo.com'));

        $ana = SolicitudDemo::where('email', 'ana@correo.com')->sole();
        $this->assertSame(3, $ana->ingresos);
        $this->assertTrue($ana->entro());
        $this->assertSame('2026-10-10', $ana->demo_expira_at->toDateString());
    }

    public function test_si_la_demo_no_responde_el_panel_abre_igual(): void
    {
        SolicitudDemo::create(['nombre' => 'Dra. Ana López', 'especialidad' => 'Pediatría', 'email' => 'ana@correo.com', 'estado' => 'enviada', 'demo_expira_at' => now()->addDay()]);
        Http::fake(['demo.clinea.app/*' => Http::response('caído', 500)]);

        $this->actingAs(User::factory()->create())->get('/cuentas/demos')
            ->assertOk()
            ->assertSee('Dra. Ana López')
            ->assertSee('✗ No ha entrado');
    }

    private function conDemoHonduras(): void
    {
        config(['clinea.demo.hn' => ['url' => 'https://demohn.clinea.app', 'token' => 'tok-hn']]);
        Http::fake([
            'demohn.clinea.app/api/accesos-demo' => Http::response([
                'url' => 'https://demohn.clinea.app/login', 'correo' => 'ana@correo.com', 'password' => 'Clave12345',
                'expira_en' => '2026-10-10T10:00:00-06:00', 'nuevo' => true,
            ], 201),
            'demo.clinea.app/api/accesos-demo' => Http::response([
                'url' => 'https://demo.clinea.app/login', 'correo' => 'ana@correo.com', 'password' => 'Clave12345',
                'expira_en' => '2026-10-10T10:00:00-06:00', 'nuevo' => true,
            ], 201),
        ]);
    }

    public function test_un_doctor_de_honduras_va_a_la_demo_de_honduras(): void
    {
        $this->conDemoHonduras();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['whatsapp' => '+504 9000 0000']))->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://demohn.clinea.app/api/accesos-demo'
            && $r->hasHeader('Authorization', 'Bearer tok-hn')
            && $r['pais'] === 'HN');
        Http::assertNotSent(fn ($r) => str_starts_with($r->url(), 'https://demo.clinea.app'));
        $this->assertSame('hn', SolicitudDemo::sole()->instancia);
        Mail::assertSent(DemoLista::class, fn (DemoLista $m) => str_contains($m->render(), 'https://demohn.clinea.app/login'));
    }

    public function test_el_salvador_y_los_demas_siguen_en_la_demo_de_siempre(): void
    {
        $this->conDemoHonduras();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'SV'])->post('/demo', $this->datos(['email' => 'sv@correo.com']))->assertOk();
        $this->withServerVariables(['GEOIP_COUNTRY' => 'GT'])->post('/demo', $this->datos(['email' => 'gt@correo.com', 'whatsapp' => '+502 5000 0000']))->assertOk();
        // Con IP de Honduras manda el GeoIP aunque el WhatsApp sea de otro lado, y al revés.
        $this->withServerVariables(['GEOIP_COUNTRY' => 'SV'])->post('/demo', $this->datos(['email' => 'mix@correo.com', 'whatsapp' => '+504 9000 0000']))->assertOk();

        $this->assertSame(['sv'], SolicitudDemo::pluck('instancia')->unique()->values()->all());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'demohn'));
    }

    public function test_sin_geoip_decide_el_prefijo_del_whatsapp(): void
    {
        $this->conDemoHonduras();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'US'])->post('/demo', $this->datos(['whatsapp' => '+504 9000-0000']))->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://demohn.clinea.app/api/accesos-demo' && $r['pais'] === 'HN');
        $d = SolicitudDemo::sole();
        $this->assertSame('hn', $d->instancia);
        $this->assertSame('US', $d->pais); // el lead guarda de dónde vino de verdad
    }

    public function test_sin_token_de_honduras_todos_van_a_la_de_el_salvador(): void
    {
        $this->fakeDemo();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['whatsapp' => '+504 9000 0000']))->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://demo.clinea.app/api/accesos-demo' && $r['pais'] === 'HN');
        $this->assertSame('sv', SolicitudDemo::sole()->instancia);
    }

    private function conDemosDentales(): void
    {
        config([
            'clinea.demo.hn' => ['url' => 'https://demohn.clinea.app', 'token' => 'tok-hn'],
            'clinea.demo.odonto' => ['url' => 'https://odonto.clinea.app', 'token' => 'tok-odonto'],
            'clinea.demo.odontohn' => ['url' => 'https://odontohn.clinea.app', 'token' => 'tok-odontohn'],
        ]);
        $acceso = fn ($host) => Http::response([
            'url' => "https://{$host}/login", 'correo' => 'ana@correo.com', 'password' => 'Clave12345',
            'expira_en' => '2026-10-10T10:00:00-06:00', 'nuevo' => true,
        ], 201);
        Http::fake(collect(['demo', 'demohn', 'odonto', 'odontohn'])
            ->mapWithKeys(fn ($h) => ["{$h}.clinea.app/api/accesos-demo" => $acceso("{$h}.clinea.app")])->all());
    }

    public function test_un_odontologo_va_a_la_demo_dental_de_su_pais(): void
    {
        $this->conDemosDentales();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'SV'])->post('/demo', $this->datos(['especialidad' => 'Odontología', 'email' => 'sv@correo.com']))->assertOk();
        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['especialidad' => 'Odontología', 'email' => 'hn@correo.com', 'whatsapp' => '+504 9000 0000']))->assertOk();
        // Un médico de Honduras sigue yendo a la médica de Honduras.
        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['email' => 'med@correo.com', 'whatsapp' => '+504 9000 0001']))->assertOk();

        $this->assertSame(['odonto', 'odontohn', 'hn'], SolicitudDemo::orderBy('id')->pluck('instancia')->all());
        Http::assertSent(fn ($r) => $r->url() === 'https://odonto.clinea.app/api/accesos-demo' && $r->hasHeader('Authorization', 'Bearer tok-odonto') && $r['especialidad'] === 'Odontología');
        Http::assertSent(fn ($r) => $r->url() === 'https://odontohn.clinea.app/api/accesos-demo' && $r->hasHeader('Authorization', 'Bearer tok-odontohn') && $r['pais'] === 'HN');
        Mail::assertSent(DemoLista::class, fn (DemoLista $m) => str_contains($m->render(), 'https://odontohn.clinea.app/login'));

        $this->actingAs(User::factory()->create())->get('/cuentas/demos')->assertSee('en odontohn.clinea.app')->assertSee('en odonto.clinea.app');
    }

    public function test_sin_demo_dental_el_odontologo_va_a_la_medica(): void
    {
        $this->conDemoHonduras();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['especialidad' => 'Odontología', 'whatsapp' => '+504 9000 0000']))->assertOk();

        $this->assertSame('hn', SolicitudDemo::sole()->instancia);
    }

    public function test_sin_la_dental_de_honduras_va_a_la_dental_de_el_salvador(): void
    {
        $this->conDemosDentales();
        config(['clinea.demo.odontohn.token' => null]);

        $this->withServerVariables(['GEOIP_COUNTRY' => 'HN'])->post('/demo', $this->datos(['especialidad' => 'Odontología', 'whatsapp' => '+504 9000 0000']))->assertOk();

        $this->assertSame('odonto', SolicitudDemo::sole()->instancia);
        Http::assertSent(fn ($r) => $r->url() === 'https://odonto.clinea.app/api/accesos-demo' && $r['pais'] === 'HN');
    }

    public function test_la_demo_dental_lleva_la_guia_y_no_promete_el_recorrido(): void
    {
        $this->conDemosDentales();

        $this->withServerVariables(['GEOIP_COUNTRY' => 'SV'])->post('/demo', $this->datos(['especialidad' => 'Odontología']))->assertOk();

        Mail::assertSent(DemoLista::class, function (DemoLista $m) {
            $html = $m->render();

            return $m->esDental() && count($m->attachments()) === 1
                && str_contains($html, 'cuatro pacientes de ejemplo')
                && ! str_contains($html, 'recorrido');
        });
    }

    public function test_reenvia_la_demo_dental_a_los_odontologos_que_tenian_la_medica(): void
    {
        $this->conDemosDentales();
        $base = ['especialidad' => 'Odontología', 'estado' => 'enviada', 'demo_expira_at' => now()->addDay()];
        SolicitudDemo::create($base + ['nombre' => 'Rosa Ruiz', 'email' => 'rosa@correo.com', 'whatsapp' => '+504 9834 8750', 'pais' => 'HN', 'instancia' => 'sv']);
        SolicitudDemo::create($base + ['nombre' => 'Rosa Ruiz', 'email' => 'rosa@correo.com', 'whatsapp' => '+504 9834 8750', 'pais' => 'HN', 'instancia' => 'sv']);
        SolicitudDemo::create($base + ['nombre' => 'Dr. Luis Paz', 'email' => 'luis@correo.com', 'whatsapp' => '+503 7000 0000', 'pais' => 'SV', 'instancia' => 'sv']);
        SolicitudDemo::create(['especialidad' => 'Pediatría'] + $base + ['nombre' => 'Dra. Ana', 'email' => 'ana@correo.com', 'instancia' => 'sv']);

        // Sin --enviar no toca nada.
        $this->artisan('cuentas:demo-dental')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(4, SolicitudDemo::count());

        $this->artisan('cuentas:demo-dental', ['--enviar' => true])->assertSuccessful();

        // Una por correo, a la demo dental de su país, y la de pediatría no.
        $this->assertSame(['odontohn', 'odonto'], SolicitudDemo::where('id', '>', 4)->orderBy('id')->pluck('instancia')->sort()->reverse()->values()->all());
        Mail::assertSentCount(2);
        Mail::assertSent(DemoLista::class, fn (DemoLista $m) => $m->reenvio && $m->hasTo('rosa@correo.com')
            && str_contains($m->render(), 'ya tiene odontograma') && str_contains($m->render(), 'https://odontohn.clinea.app/login'));

        // Repetirlo no les vuelve a escribir.
        $this->artisan('cuentas:demo-dental', ['--enviar' => true])->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_guatemala_tiene_sus_demos(): void
    {
        config([
            'clinea.demo.gt' => ['url' => 'https://demogt.clinea.app', 'token' => 'tok-gt'],
            'clinea.demo.odonto' => ['url' => 'https://odonto.clinea.app', 'token' => 'tok-odonto'],
            'clinea.demo.odontogt' => ['url' => 'https://odontogt.clinea.app', 'token' => 'tok-odontogt'],
        ]);
        $acceso = fn ($host) => Http::response(['url' => "https://{$host}/login", 'correo' => 'x@correo.com', 'password' => 'Clave12345', 'expira_en' => '2026-10-10T10:00:00-06:00', 'nuevo' => true], 201);
        Http::fake(collect(['demo', 'demogt', 'odonto', 'odontogt'])->mapWithKeys(fn ($h) => ["{$h}.clinea.app/api/accesos-demo" => $acceso("{$h}.clinea.app")])->all());

        // Por IP de Guatemala, y sin GeoIP por el +502 del WhatsApp.
        $this->withServerVariables(['GEOIP_COUNTRY' => 'GT'])->post('/demo', $this->datos(['email' => 'a@correo.com', 'whatsapp' => '+502 5515 2456']))->assertOk();
        $this->withServerVariables(['GEOIP_COUNTRY' => 'US'])->post('/demo', $this->datos(['email' => 'b@correo.com', 'whatsapp' => '+502 5515 2456']))->assertOk();
        $this->withServerVariables(['GEOIP_COUNTRY' => 'GT'])->post('/demo', $this->datos(['email' => 'c@correo.com', 'especialidad' => 'Odontología', 'whatsapp' => '+502 5515 2456']))->assertOk();

        $this->assertSame(['gt', 'gt', 'odontogt'], SolicitudDemo::orderBy('id')->pluck('instancia')->all());
        Http::assertSent(fn ($r) => $r->url() === 'https://demogt.clinea.app/api/accesos-demo' && $r->hasHeader('Authorization', 'Bearer tok-gt') && $r['pais'] === 'GT');
    }

    public function test_la_actividad_se_pregunta_a_la_demo_donde_quedo_cada_uno(): void
    {
        config(['clinea.demo.hn' => ['url' => 'https://demohn.clinea.app', 'token' => 'tok-hn']]);
        $base = ['especialidad' => 'Pediatría', 'estado' => 'enviada', 'demo_expira_at' => now()->addDay()];
        SolicitudDemo::create($base + ['nombre' => 'Dra. Ana López', 'email' => 'ana@correo.com', 'instancia' => 'sv']);
        SolicitudDemo::create($base + ['nombre' => 'Dr. Luis Paz', 'email' => 'luis@correo.com', 'instancia' => 'hn']);

        $entro = fn ($correo) => ['correo' => $correo, 'primer_ingreso_en' => '2026-10-03T08:00:00-06:00', 'ultimo_ingreso_en' => '2026-10-03T09:00:00-06:00', 'ingresos' => 2, 'expira_en' => '2026-10-10T08:00:00-06:00'];
        Http::fake([
            'demo.clinea.app/api/accesos-demo/actividad*' => Http::response(['demos' => [$entro('ana@correo.com')]]),
            'demohn.clinea.app/api/accesos-demo/actividad*' => Http::response(['demos' => [$entro('luis@correo.com')]]),
        ]);

        $this->artisan('cuentas:actividad-demos')->assertSuccessful();

        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://demohn.clinea.app/api/accesos-demo/actividad')
            && $r->hasHeader('Authorization', 'Bearer tok-hn')
            && str_contains(urldecode($r->url()), 'luis@correo.com')
            && ! str_contains(urldecode($r->url()), 'ana@correo.com'));
        $this->assertSame([2, 2], SolicitudDemo::orderBy('id')->pluck('ingresos')->all());
    }
}
