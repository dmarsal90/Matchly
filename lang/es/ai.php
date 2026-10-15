<?php

declare(strict_types=1);

return [
    'title' => 'Inteligencia artificial',
    'description' => 'La IA estructura tu CV, resume tu GitHub y extrae los requisitos de las ofertas. Nunca inventa experiencia: tú revisas todo antes de guardarlo.',
    'status' => [
        'available' => 'La IA está disponible.',
        'unavailable' => 'Ahora mismo no hay ningún proveedor de IA disponible. Puedes usar tu propia clave de Anthropic.',
    ],
    'consent' => [
        'given' => 'Has dado tu consentimiento para que la IA procese tus datos.',
        'missing' => 'Aún no has dado tu consentimiento para que la IA procese tus datos. Se te pedirá cuando uses una función de IA.',
        'manage' => 'Gestionar en Privacidad',
    ],
    'usage' => [
        'title' => 'Uso este mes',
        'calls' => '{0} Ninguna llamada|{1} :count llamada|[2,*] :count llamadas',
        'budget' => 'Presupuesto incluido: :used de :limit USD',
        'own_key_cost' => 'Coste con tu clave: :cost USD (lo factura Anthropic directamente).',
        'tasks' => [
            'extract_job_requirements' => 'Requisitos de ofertas',
            'structure_cv' => 'Estructurar CV',
            'summarize_github' => 'Resumen de GitHub',
            'tailor_cv' => 'Adaptar CV',
            'cover_letter' => 'Carta de presentación',
        ],
    ],
    'key' => [
        'title' => 'Tu clave de Anthropic (opcional)',
        'description' => 'Con tu propia clave, tus llamadas no consumen el presupuesto incluido. Se guarda cifrada y nunca se vuelve a mostrar.',
        'label' => 'Clave de API',
        'placeholder' => 'sk-ant-…',
        'current' => 'Clave guardada: :hint',
        'save' => 'Guardar clave',
        'remove' => 'Quitar clave',
        'remove_confirm' => '¿Quitar tu clave de Anthropic?',
    ],
    'key_saved' => 'Clave guardada.',
    'key_removed' => 'Clave eliminada.',
    'errors' => [
        'disabled' => 'La IA está desactivada.',
        'no_provider' => 'No hay ningún proveedor de IA configurado.',
        'budget_exceeded' => 'Has agotado el presupuesto de IA de este mes. Puedes añadir tu propia clave de Anthropic en Ajustes › IA.',
        'rate_limited' => 'Has hecho demasiadas peticiones de IA. Espera un poco.',
        'failed' => 'Los proveedores de IA no han respondido correctamente. Inténtalo más tarde.',
    ],
];
