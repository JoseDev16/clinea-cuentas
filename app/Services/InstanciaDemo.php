<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pide a una instancia demo de Clinea un acceso de demostración (7 días).
 * Hay cuatro: «sv» (demo.clinea.app, la de siempre), «hn» (demohn.clinea.app,
 * configurada para Honduras) y sus gemelas dentales «odonto»
 * (odonto.clinea.app) y «odontohn» (odontohn.clinea.app), con odontograma y
 * tratamientos. Del otro lado: App\Http\Controllers\Api\AccesoDemoController del EMR.
 */
class InstanciaDemo
{
    public const CUENTA_EXISTENTE = 'cuenta_existente';

    public const INSTANCIAS = ['sv', 'hn', 'odonto', 'odontohn'];

    /** La especialidad del formulario que va a las demos dentales. */
    public const ODONTOLOGIA = 'Odontología';

    public function configurada(string $instancia = 'sv'): bool
    {
        return filled($this->config($instancia)['token']);
    }

    /**
     * A qué demo va un doctor según su país y su especialidad. Los odontólogos
     * van a la demo dental (la de Honduras si es de allá); los demás, a la
     * médica de su país. Una demo sin token no existe: se cae a la siguiente
     * (dental de Honduras → dental de El Salvador → médica).
     */
    public function para(?string $pais, ?string $especialidad = null): string
    {
        $hn = strtoupper((string) $pais) === 'HN';

        if ($especialidad === self::ODONTOLOGIA) {
            if ($hn && $this->configurada('odontohn')) {
                return 'odontohn';
            }
            if ($this->configurada('odonto')) {
                return 'odonto';
            }
        }

        return $hn && $this->configurada('hn') ? 'hn' : 'sv';
    }

    /** Dónde vive cada demo, para el panel («demohn.clinea.app»). */
    public function dominio(string $instancia): string
    {
        return (string) parse_url((string) $this->config($instancia)['url'], PHP_URL_HOST);
    }

    /**
     * @return array{url: string, correo: string, password: string, expira_en: string, nuevo: bool}
     *
     * @throws RuntimeException con el mensaje CUENTA_EXISTENTE si el correo es de una cuenta real
     */
    public function crearAcceso(string $nombre, string $correo, string $especialidad, ?string $pais = null, string $instancia = 'sv'): array
    {
        if (! $this->configurada($instancia)) {
            throw new RuntimeException("Falta el token de la demo «{$instancia}» en el .env");
        }

        try {
            $r = $this->cliente($instancia)
                ->timeout(20)
                ->post('/api/accesos-demo', [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'especialidad' => $especialidad,
                    // El membrete de muestra lleva una dirección de ese país.
                    'pais' => $pais,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 409) {
                throw new RuntimeException(self::CUENTA_EXISTENTE);
            }
            throw $e;
        }

        if (empty($r['password']) || empty($r['url']) || empty($r['expira_en'])) {
            throw new RuntimeException('La instancia demo no devolvió el acceso: '.json_encode(array_keys($r ?? [])));
        }

        return $r;
    }

    /**
     * Si cada doctor entró a su demo y cuándo, por correo. Los correos que en
     * esa demo no son (o ya no son) un acceso de demostración no vienen.
     *
     * @param  list<string>  $correos
     * @return array<string, array{primer_ingreso_en: ?string, ultimo_ingreso_en: ?string, ingresos: int, expira_en: ?string}>
     */
    public function actividad(array $correos, int $timeout = 15, string $instancia = 'sv'): array
    {
        if (! $this->configurada($instancia) || $correos === []) {
            return [];
        }

        $r = $this->cliente($instancia)
            ->timeout($timeout)
            ->get('/api/accesos-demo/actividad', ['correos' => array_values($correos)])
            ->throw()
            ->json('demos', []);

        return collect($r)->keyBy(fn ($d) => mb_strtolower($d['correo']))->all();
    }

    private function cliente(string $instancia): PendingRequest
    {
        $c = $this->config($instancia);

        return Http::baseUrl(rtrim((string) $c['url'], '/'))
            ->withToken($c['token'])
            ->acceptJson();
    }

    /** @return array{url: ?string, token: ?string} */
    private function config(string $instancia): array
    {
        return in_array($instancia, ['hn', 'odonto', 'odontohn'], true)
            ? config("clinea.demo.{$instancia}")
            : ['url' => config('clinea.demo.url'), 'token' => config('clinea.demo.token')];
    }
}
