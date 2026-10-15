<?php

declare(strict_types=1);

return [
    'title' => 'Privacidade e dados',
    'description' => 'Escolha que dados tratamos e exerça os seus direitos.',
    'consents_title' => 'Consentimentos',
    'consents_description' => 'Cada utilização opcional dos seus dados precisa da sua autorização explícita. Pode retirá-la a qualquer momento.',
    'granted_since' => 'Concedido em :date',
    'consent_saved' => 'Preferência guardada.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Processar o meu currículo',
            'description' => 'Extrair texto, experiência e competências dos currículos que carregar.',
            'revoke_warning' => 'Ao retirar, todos os seus currículos serão apagados.',
        ],
        'github_import' => [
            'title' => 'Importar do GitHub',
            'description' => 'Analisar os seus repositórios públicos para detetar tecnologias.',
            'revoke_warning' => 'Ao retirar, os dados importados do GitHub serão apagados.',
        ],
        'linkedin_import' => [
            'title' => 'Importar do LinkedIn',
            'description' => 'Ler os ficheiros de perfil da sua exportação de dados do LinkedIn.',
            'revoke_warning' => 'Ao retirar, os dados importados do LinkedIn serão apagados.',
        ],
        'ai_processing' => [
            'title' => 'Usar IA com os meus dados',
            'description' => 'Enviar o seu perfil a fornecedores de IA para gerar cartas e currículos adaptados.',
            'revoke_warning' => 'Deixaremos de usar IA com os seus dados.',
        ],
        'email_alerts' => [
            'title' => 'Alertas por e-mail',
            'description' => 'Receber e-mails com ofertas que encaixam nas suas pesquisas guardadas.',
            'revoke_warning' => 'Deixará de receber alertas por e-mail.',
        ],
    ],
    'export_title' => 'Descarregar os meus dados',
    'export_description' => 'Um ZIP com tudo o que guardamos sobre si (JSON legível) e os seus ficheiros originais.',
    'export_button' => 'Descarregar ZIP',
    'delete_title' => 'Eliminar a minha conta',
    'delete_description' => 'Elimine a sua conta e todos os seus dados permanentemente em Definições › Conta.',
    'delete_link' => 'Ir para a eliminação da conta',
    'policy_link' => 'Política de privacidade',
    'version' => 'Versão da política: :version',
];
