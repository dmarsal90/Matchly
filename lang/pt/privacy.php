<?php

declare(strict_types=1);

return [
    'title' => 'Privacidade e dados',
    'description' => 'Escolha quais dados tratamos e exerça seus direitos.',
    'consents_title' => 'Consentimentos',
    'consents_description' => 'Cada uso opcional dos seus dados precisa da sua permissão explícita. Você pode retirá-la a qualquer momento.',
    'granted_since' => 'Concedido em :date',
    'consent_saved' => 'Preferência salva.',
    'purposes' => [
        'cv_processing' => [
            'title' => 'Processar meu currículo',
            'description' => 'Extrair texto, experiência e habilidades dos currículos enviados.',
            'revoke_warning' => 'Ao retirar, todos os seus currículos serão apagados.',
        ],
        'github_import' => [
            'title' => 'Importar do GitHub',
            'description' => 'Analisar seus repositórios públicos para detectar tecnologias.',
            'revoke_warning' => 'Ao retirar, os dados importados do GitHub serão apagados.',
        ],
        'linkedin_import' => [
            'title' => 'Importar do LinkedIn',
            'description' => 'Ler os arquivos de perfil da sua exportação de dados do LinkedIn.',
            'revoke_warning' => 'Ao retirar, os dados importados do LinkedIn serão apagados.',
        ],
        'ai_processing' => [
            'title' => 'Usar IA com meus dados',
            'description' => 'Enviar seu perfil a provedores de IA para gerar cartas e currículos adaptados.',
            'revoke_warning' => 'Deixaremos de usar IA com seus dados.',
        ],
        'email_alerts' => [
            'title' => 'Alertas por e-mail',
            'description' => 'Receber e-mails com vagas que combinam com suas buscas salvas.',
            'revoke_warning' => 'Você deixará de receber alertas por e-mail.',
        ],
    ],
    'export_title' => 'Baixar meus dados',
    'export_description' => 'Um ZIP com tudo o que guardamos sobre você (JSON legível) e seus arquivos originais.',
    'export_button' => 'Baixar ZIP',
    'delete_title' => 'Excluir minha conta',
    'delete_description' => 'Exclua sua conta e todos os seus dados permanentemente em Configurações › Conta.',
    'delete_link' => 'Ir para exclusão da conta',
    'policy_link' => 'Política de privacidade',
    'version' => 'Versão da política: :version',
];
