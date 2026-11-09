<?php

namespace Tests\Feature;

use App\Http\Controllers\AgentesController;
use App\Mail\DemoLista;
use App\Models\SolicitudDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** POST /cuentas/api/demo: el agente de ventas crea la demo desde el chat. */
class AgentesDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'clinea.agentes_token' => 'tok-agentes',
            'clinea.demo.url' => 'https://demo.clinea.app',
            'clinea.demo.token' => 'tok-demo',
        ]);
        Mail::fake();
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Dra. Ana López', 'especialidad' => 'ginecóloga', 'correo' => 'Ana@Correo.com', 'telefono' => '+503 7884 5819'], $extra);
    }

    public function test_crea_la_demo_y_devuelve_el_acceso(): void
    {
        Http::fake(['demo.clinea.app/api/accesos-demo' => Http::response([
            'url' => 'https://demo.clinea.app/entrar', 'correo' => 'ana@correo.com', 'password' => 'Secreta123', 'expira_en' => now()->addDay()->toIso8601String(), 'nuevo' => true,
        ])]);

        $r = $this->withToken('tok-agentes')->postJson('/cuentas/api/demo', $this->datos())->assertCreated();

        $this->assertSame('creada', $r->json('resultado'));
        $this->assertSame('Secreta123', $r->json('contrasena'));
        $d = SolicitudDemo::sole();
        $this->assertSame('enviada', $d->estado);
        $this->assertSame('Ginecología y obstetricia', $d->especialidad);
        $this->assertSame('ana@correo.com', $d->email);
        $this->assertSame('SV', $d->pais);
        Mail::assertSent(DemoLista::class);
    }

    public function test_si_el_telefono_ya_tiene_demo_vigente_no_crea_otra(): void
    {
        SolicitudDemo::create(['nombre' => 'Ana', 'especialidad' => 'Pediatría', 'email' => 'ana@correo.com', 'pais' => 'SV',
            'estado' => 'enviada', 'whatsapp' => '78845819', 'instancia' => 'sv', 'demo_expira_at' => now()->addHours(5)]);
        Http::fake();

        $r = $this->withToken('tok-agentes')->postJson('/cuentas/api/demo', $this->datos())->assertOk();

        $this->assertSame('ya_tiene_demo', $r->json('resultado'));
        $this->assertSame(1, SolicitudDemo::count());
        Http::assertNothingSent();
    }

    public function test_correo_de_cuenta_real(): void
    {
        Http::fake(['demo.clinea.app/*' => Http::response(['message' => 'existe'], 409)]);

        $r = $this->withToken('tok-agentes')->postJson('/cuentas/api/demo', $this->datos())->assertStatus(409);
        $this->assertSame('correo_registrado', $r->json('resultado'));
    }

    public function test_sin_token_no_crea_nada(): void
    {
        $this->postJson('/cuentas/api/demo', $this->datos())->assertUnauthorized();
        $this->assertSame(0, SolicitudDemo::count());
    }

    public function test_especialidad_dicha_en_el_chat(): void
    {
        $this->assertSame('Odontología', AgentesController::especialidad('Soy dentista'));
        $this->assertSame('Medicina interna', AgentesController::especialidad('internista'));
        $this->assertSame('Pediatría', AgentesController::especialidad('pediatria'));
        $this->assertSame('Otra especialidad', AgentesController::especialidad('urólogo'));
    }
}
