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
        // Honduras y Guatemala: Wompi solo cobra en USD y el banco del cliente gana algo en
        // la conversión, así que se cobra un poco menos ($8 / $13) y la landing
        // anuncia un precio redondo en lempiras (L240 / L370) que lo cubre.
        'HN' => [
            // «anunciado»: el precio en lempiras de la landing; el cliente nunca paga más.
            'expediente' => ['nombre' => 'Expediente clínico', 'monto' => (float) env('CLINEA_HN_EXPEDIENTE', 8.00), 'anunciado' => 'L240'],
            'whatsapp' => ['nombre' => 'Expediente + WhatsApp', 'monto' => (float) env('CLINEA_HN_WHATSAPP', 13.00), 'anunciado' => 'L370'],
        ],
        // Guatemala: misma lógica. $8 / $13 ≈ Q61 / Q99 a ~Q7.63 por dólar; se
        // anuncia Q65 / Q105, que aguanta hasta ~Q8.08 por dólar.
        'GT' => [
            'expediente' => ['nombre' => 'Expediente clínico', 'monto' => (float) env('CLINEA_GT_EXPEDIENTE', 8.00), 'anunciado' => 'Q65'],
            'whatsapp' => ['nombre' => 'Expediente + WhatsApp', 'monto' => (float) env('CLINEA_GT_WHATSAPP', 13.00), 'anunciado' => 'Q105'],
        ],
    ],

    'paises' => [
        'SV' => 'El Salvador',
        'HN' => 'Honduras',
        'GT' => 'Guatemala',
    ],

    // Moneda local de los países que pagan en dólares con precio anunciado en su moneda.
    'monedas_locales' => [
        'HN' => 'lempiras',
        'GT' => 'quetzales',
    ],

    // A quién avisar de contrataciones, pagos y atrasos (separados por coma).
    'avisos_a' => array_filter(array_map('trim', explode(',', (string) env('CLINEA_AVISOS_A', 'hello@fstudios.dev')))),

    // Días de margen después del día de cobro antes de marcar una suscripción como atrasada.
    'dias_gracia' => (int) env('CLINEA_DIAS_GRACIA', 3),

    // Wompi no admite el día 29-31 en todos los meses; se cobra a más tardar el 28.
    'dia_cobro_maximo' => 28,

    'whatsapp_soporte' => env('CLINEA_WHATSAPP', '50366781544'),
    'correo_soporte' => env('CLINEA_CORREO_SOPORTE', 'hello@fstudios.dev'),

    // Así se escribe la marca en todo lo que ve el cliente: sin tilde.
    'marca' => 'Clinea',

    // Horas que le prometemos al cliente, desde que se suscribe en Wompi,
    // para tener lista su instancia y que un técnico lo contacte.
    'horas_instancia' => (int) env('CLINEA_HORAS_INSTANCIA', 3),

    // Cuántas horas después de pedir el enlace se sigue consultando Wompi cada
    // minuto para detectar la suscripción casi al instante.
    'horas_vigilancia' => (int) env('CLINEA_HORAS_VIGILANCIA', 48),

    // «Prueba Clinea 24 horas»: la landing pide la demo y aquí se crea el
    // acceso en la instancia demo (POST {url}/api/accesos-demo con el token).
    'demo' => [
        'url' => env('CLINEA_DEMO_URL', 'https://demo.clinea.app'),
        'token' => env('CLINEA_DEMO_API_TOKEN'),
        'horas' => 24,
        // Solicitudes con el mismo correo en 24 h (cada una genera otra contraseña).
        'maximo_por_correo' => 2,
    ],

    // Lista del formulario de demo; la landing muestra exactamente estas.
    'especialidades' => [
        'Medicina general', 'Medicina familiar', 'Medicina interna', 'Pediatría',
        'Ginecología y obstetricia', 'Cardiología', 'Dermatología',
        'Ortopedia y traumatología', 'Neurología', 'Gastroenterología',
        'Endocrinología', 'Psicología', 'Psiquiatría', 'Nutrición',
        'Odontología', 'Fisioterapia', 'Otra especialidad',
    ],

    'direccion_fstudios' => 'Plaza El Triángulo, Avenida Roosevelt Sur, San Miguel, El Salvador',

    // Orígenes desde los que se acepta el formulario de contratar (la landing).
    'origenes' => array_filter(array_map('trim', explode(',', (string) env('CLINEA_ORIGENES', 'https://clinea.app,https://www.clinea.app')))),
];
