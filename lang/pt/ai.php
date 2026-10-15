<?php

declare(strict_types=1);

return [
    'title' => 'Inteligência artificial',
    'description' => 'A IA estrutura o seu CV, resume o seu GitHub e extrai os requisitos das ofertas. Nunca inventa experiência: revê tudo antes de ser guardado.',
    'status' => [
        'available' => 'A IA está disponível.',
        'unavailable' => 'Neste momento não há nenhum fornecedor de IA disponível. Pode usar a sua própria chave da Anthropic.',
    ],
    'consent' => [
        'given' => 'Deu o seu consentimento para que a IA processe os seus dados.',
        'missing' => 'Ainda não deu o seu consentimento para que a IA processe os seus dados. Ser-lhe-á pedido quando usar uma função de IA.',
        'manage' => 'Gerir em Privacidade',
    ],
    'usage' => [
        'title' => 'Utilização este mês',
        'calls' => '{0} Nenhuma chamada|{1} :count chamada|[2,*] :count chamadas',
        'budget' => 'Orçamento incluído: :used de :limit USD',
        'own_key_cost' => 'Custo com a sua chave: :cost USD (faturado diretamente pela Anthropic).',
        'tasks' => [
            'extract_job_requirements' => 'Requisitos de ofertas',
            'structure_cv' => 'Estruturar CV',
            'summarize_github' => 'Resumo do GitHub',
            'tailor_cv' => 'Adaptar CV',
            'cover_letter' => 'Carta de apresentação',
        ],
    ],
    'key' => [
        'title' => 'A sua chave da Anthropic (opcional)',
        'description' => 'Com a sua própria chave, as suas chamadas não gastam o orçamento incluído. É guardada cifrada e nunca volta a ser mostrada.',
        'label' => 'Chave de API',
        'placeholder' => 'sk-ant-…',
        'current' => 'Chave guardada: :hint',
        'save' => 'Guardar chave',
        'remove' => 'Remover chave',
        'remove_confirm' => 'Remover a sua chave da Anthropic?',
    ],
    'key_saved' => 'Chave guardada.',
    'key_removed' => 'Chave removida.',
    'errors' => [
        'disabled' => 'A IA está desativada.',
        'no_provider' => 'Não há nenhum fornecedor de IA configurado.',
        'budget_exceeded' => 'Esgotou o orçamento de IA deste mês. Pode adicionar a sua própria chave da Anthropic em Definições › IA.',
        'rate_limited' => 'Demasiados pedidos de IA. Aguarde um pouco.',
        'failed' => 'Os fornecedores de IA não responderam corretamente. Tente mais tarde.',
    ],
];
