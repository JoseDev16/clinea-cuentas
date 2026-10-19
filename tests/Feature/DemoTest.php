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
}
