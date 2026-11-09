<?php

namespace Tests\Feature;

use App\Models\SolicitudDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** /cuentas/api/contacto: lo que FSTUDIOS Agentes sabe de un teléfono. */
class AgentesContactoTest extends TestCase
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
    }

    public function test_devuelve_las_demos_del_telefono_con_su_historial(): void
    {
        SolicitudDemo::create(['nombre' => 'Dr. Luis Pérez', 'especialidad' => 'Pediatría', 'email' => 'luis@correo.com',
            'pais' => 'SV', 'estado' => 'enviada', 'whatsapp' => '+503 7884 5819', 'instancia' => 'sv']);
        SolicitudDemo::create(['nombre' => 'Otra', 'especialidad' => 'Pediatría', 'email' => 'otra@correo.com',
            'pais' => 'SV', 'estado' => 'enviada', 'whatsapp' => '70001111', 'instancia' => 'sv']);

        Http::fake(['demo.clinea.app/api/accesos-demo/historial*' => Http::response([
            'perfil' => ['ingresos' => 2],
            'eventos' => [['en' => '2026-11-01T10:00:00-06:00', 'que' => 'Finalizó una consulta']],
        ])]);

        $r = $this->withToken('tok-agentes')->getJson('/cuentas/api/contacto?telefono=78845819')->assertOk();

        $this->assertCount(1, $r->json('demos'));
        $this->assertSame('Dr. Luis Pérez', $r->json('demos.0.nombre'));
        $this->assertSame('Finalizó una consulta', $r->json('demos.0.historial.eventos.0.que'));
        $this->assertNull($r->json('cliente'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'correo=luis%40correo.com') && $req->hasHeader('Authorization', 'Bearer tok-demo'));
    }

    public function test_sin_token_o_con_token_falso_no_responde(): void
    {
        $this->getJson('/cuentas/api/contacto?telefono=78845819')->assertUnauthorized();
        $this->withToken('falso')->getJson('/cuentas/api/contacto?telefono=78845819')->assertUnauthorized();

        config(['clinea.agentes_token' => null]);
        $this->withToken('tok-agentes')->getJson('/cuentas/api/contacto?telefono=78845819')->assertNotFound();
    }

    public function test_si_la_demo_no_responde_igual_devuelve_la_solicitud(): void
    {
        SolicitudDemo::create(['nombre' => 'Dr. Luis Pérez', 'especialidad' => 'Pediatría', 'email' => 'luis@correo.com',
            'pais' => 'SV', 'estado' => 'enviada', 'whatsapp' => '78845819', 'instancia' => 'sv']);
        Http::fake(['demo.clinea.app/*' => Http::response('caído', 500)]);

        $r = $this->withToken('tok-agentes')->getJson('/cuentas/api/contacto?telefono=%2B50378845819')->assertOk();

        $this->assertSame('Dr. Luis Pérez', $r->json('demos.0.nombre'));
        $this->assertNull($r->json('demos.0.historial'));
    }
}
