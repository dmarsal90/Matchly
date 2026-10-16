<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => '¿Toca hacer seguimiento?',
        'body' => ':company no ha respondido a tu candidatura para :title en :days días.',
        'open' => 'Abrir mis ofertas',
    ],
    'mail' => [
        'subject' => '¿Hacemos seguimiento con :company?',
        'heading' => 'Aún sin respuesta de :company',
        'intro' => 'Te presentaste a :title el :date y no has sabido nada desde entonces. Un seguimiento breve suele ayudar. Aquí tienes un borrador que puedes adaptar y enviar tú mismo:',
        'open' => 'Abrir mi candidatura',
        'reminder' => 'Es solo un borrador: nunca contactamos con las empresas por ti.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Estimado equipo de selección:', 'friendly' => 'Hola:', 'enthusiastic' => '¡Hola!'],
        'body' => 'Me presenté el :date al puesto de :title en :company y quería saber cómo avanza el proceso. Sigo muy interesado en el puesto.',
        'close' => [
            'formal' => 'Les agradezco su tiempo y quedo a su disposición para ampliar cualquier información.',
            'friendly' => 'Encantado de hablar o de enviar lo que necesitéis. ¡Gracias!',
            'enthusiastic' => '¡Me encantaría saber en qué punto estamos! ¡Muchas gracias!',
        ],
        'sign_off' => ['formal' => 'Atentamente,', 'friendly' => 'Un saludo,', 'enthusiastic' => '¡Un abrazo!'],
    ],
];
