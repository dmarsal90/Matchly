<?php

declare(strict_types=1);

return [
    'back' => 'Volver al inicio',
    'updated' => 'Última actualización: :date',
    'privacy' => [
        'title' => 'Política de privacidad',
        'intro' => 'En :app tratamos tus datos solo para ayudarte a encontrar empleo remoto y con las garantías del RGPD.',
        'sections' => [
            'controller' => [
                'title' => 'Responsable',
                'body' => 'El responsable del tratamiento es el titular de :app. Puedes contactar para cualquier cuestión de privacidad desde los datos indicados en el sitio.',
            ],
            'data' => [
                'title' => 'Qué datos tratamos',
                'body' => 'Datos de cuenta (nombre, correo), perfil profesional que tú introduces, CV que subas, datos que importes de GitHub o LinkedIn con tu consentimiento, y datos técnicos mínimos para la seguridad. No pedimos edad, género, nacionalidad ni foto.',
            ],
            'purposes' => [
                'title' => 'Para qué',
                'body' => 'Mostrarte ofertas, calcular tu compatibilidad, preparar solicitudes y enviarte alertas. Cada tratamiento opcional se basa en tu consentimiento, que puedes retirar en Ajustes › Privacidad.',
            ],
            'security' => [
                'title' => 'Seguridad',
                'body' => 'Los archivos se guardan cifrados, las comunicaciones van por HTTPS y solo tú accedes a tus datos.',
            ],
            'retention' => [
                'title' => 'Conservación',
                'body' => 'Las cuentas sin verificar se borran a los :days días. El texto extraído de los CV de cuentas inactivas se borra a los :months meses. Al eliminar tu cuenta se borra todo.',
            ],
            'rights' => [
                'title' => 'Tus derechos',
                'body' => 'Puedes acceder, rectificar, exportar y borrar tus datos desde Ajustes, y reclamar ante la autoridad de protección de datos.',
            ],
            'sources' => [
                'title' => 'Ofertas de terceros',
                'body' => 'Las ofertas proceden de fuentes públicas y se enlazan a su origen. Al postularte, tratas directamente con la empresa.',
            ],
        ],
    ],
    'terms' => [
        'title' => 'Términos de uso',
        'intro' => ':app agrega ofertas públicas de empleo remoto y te ayuda a preparar candidaturas.',
        'sections' => [
            'service' => [
                'title' => 'El servicio',
                'body' => 'Las ofertas pertenecen a sus fuentes originales y se muestran con atribución y enlace. No garantizamos su exactitud ni disponibilidad.',
            ],
            'account' => [
                'title' => 'Tu cuenta',
                'body' => 'Eres responsable de la veracidad de tu perfil y de mantener tus credenciales seguras.',
            ],
            'ai' => [
                'title' => 'Contenido generado',
                'body' => 'Las cartas y CV adaptados solo usan los datos de tu perfil. Revísalos siempre antes de enviarlos.',
            ],
            'applications' => [
                'title' => 'Candidaturas',
                'body' => 'Nunca enviamos candidaturas en tu nombre: tú decides y te postulas en la web oficial de cada oferta.',
            ],
        ],
    ],
];
