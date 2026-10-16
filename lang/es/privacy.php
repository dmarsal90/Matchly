<?php

declare(strict_types=1);

return [
    'title' => 'Privacidad y datos',
    'description' => 'Decide qué datos tratamos y ejerce tus derechos.',
    'consents_title' => 'Consentimientos',
    'consents_description' => 'Cada uso opcional de tus datos necesita tu permiso explícito. Puedes retirarlo en cualquier momento.',
    'granted_since' => 'Concedido el :date',
    'consent_saved' => 'Preferencia guardada.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Procesar mi CV',
            'description' => 'Extraer texto, experiencia y habilidades de los CV que subas.',
            'revoke_warning' => 'Al retirarlo se borrarán todos tus CV.',
        ],
        'github_import' => [
            'title' => 'Importar desde GitHub',
            'description' => 'Analizar tus repositorios públicos para detectar tecnologías.',
            'revoke_warning' => 'Al retirarlo se borrarán los datos importados de GitHub.',
        ],
        'linkedin_import' => [
            'title' => 'Importar desde LinkedIn',
            'description' => 'Leer los archivos de perfil de tu exportación de datos de LinkedIn.',
            'revoke_warning' => 'Al retirarlo se borrarán los datos importados de LinkedIn.',
        ],
        'portfolio_import' => [
            'title' => 'Importar desde mi portfolio',
            'description' => 'Leer tu web pública para detectar tecnologías.',
            'revoke_warning' => 'Al retirarlo se borrarán los datos importados de tu portfolio.',
        ],
        'ai_processing' => [
            'title' => 'Usar IA con mis datos',
            'description' => 'Enviar tu perfil a proveedores de IA para generar cartas y CV adaptados.',
            'revoke_warning' => 'Dejaremos de usar IA con tus datos.',
        ],
        'email_alerts' => [
            'title' => 'Alertas por correo',
            'description' => 'Recibir correos con ofertas que encajan con tus búsquedas guardadas.',
            'revoke_warning' => 'Dejarás de recibir alertas por correo.',
        ],
    ],
    'export_title' => 'Descargar mis datos',
    'export_description' => 'Un ZIP con todo lo que guardamos sobre ti (JSON legible) y tus archivos originales.',
    'export_button' => 'Descargar ZIP',
    'delete_title' => 'Eliminar mi cuenta',
    'delete_description' => 'Borra tu cuenta y todos tus datos de forma permanente desde Ajustes › Cuenta.',
    'delete_link' => 'Ir a eliminar la cuenta',
    'policy_link' => 'Política de privacidad',
    'version' => 'Versión de la política: :version',
];
