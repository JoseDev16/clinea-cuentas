<?php

namespace App\Http\Controllers;

use App\Services\PagosUnicos;
use App\Services\Wompi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wompi avisa por POST de cada transacción exitosa de los enlaces de pago
 * único (Honduras y Guatemala). Solo se acepta si la firma wompi_hash
 * (HMAC-SHA256 del body con el API Secret) es válida.
 */
class WompiWebhookController
{
    public function __invoke(Request $request, Wompi $wompi, PagosUnicos $pagos)
    {
        $body = $request->getContent();
        if (! $wompi->firmaValida($body, $request->header('wompi_hash') ?? $request->header('wompi-hash'))) {
            Log::warning('Webhook de Wompi con firma inválida', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $datos = json_decode($body, true);
        if (! is_array($datos) || empty($datos['IdTransaccion'])) {
            return response()->json(['ok' => false], 422);
        }

        try {
            $pago = $pagos->desdeWebhook($datos);
        } catch (Throwable $e) {
            // 500: Wompi reintenta, y la revisión de respaldo lo toma igual.
            Log::error('No se pudo registrar el pago del webhook de Wompi', ['error' => $e->getMessage(), 'transaccion' => $datos['IdTransaccion']]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true, 'registrado' => (bool) $pago]);
    }
}
