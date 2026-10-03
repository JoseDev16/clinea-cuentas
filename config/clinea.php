<?php

return [

    /*
     * Planes por país. El monto es lo que Wompi cobra cada mes (en USD: la
     * cuenta de Wompi es de El Salvador). La landing (clinea.app) muestra
     * estos mismos precios; si cambias uno aquí, cámbialo también allá.
     */
    'planes' => [
        'SV' => [
            'expediente' => ['nombre' => 'Expediente clínico', 'monto' => 9.00],
            'whatsapp' => ['nombre' => 'Expediente + WhatsApp', 'monto' => 14.00],
        ],
        'HN' => [
            'expediente' => ['nombre' => 'Expediente clínico', 'monto' => (float) env('CLINEA_HN_EXPEDIENTE', 9.99)],
            'whatsapp' => ['nombre' => 'Expediente + WhatsApp', 'monto' => (float) env('CLINEA_HN_WHATSAPP', 12.99)],
        ],
    ],

    'paises' => [
        'SV' => 'El Salvador',
        'HN' => 'Honduras',
    ],

    // A quién avisar de contrataciones, pagos y atrasos (separados por coma).
    'avisos_a' => array_filter(array_map('trim', explode(',', (string) env('CLINEA_AVISOS_A', 'hello@fstudios.dev')))),

    // Días de margen después del día de cobro antes de marcar una suscripción como atrasada.
    'dias_gracia' => (int) env('CLINEA_DIAS_GRACIA', 3),

    // Wompi no admite el día 29-31 en todos los meses; se cobra a más tardar el 28.
    'dia_cobro_maximo' => 28,

    'whatsapp_soporte' => env('CLINEA_WHATSAPP', '50366781544'),

    // Orígenes desde los que se acepta el formulario de contratar (la landing).
    'origenes' => array_filter(array_map('trim', explode(',', (string) env('CLINEA_ORIGENES', 'https://clinea.app,https://www.clinea.app')))),
];
