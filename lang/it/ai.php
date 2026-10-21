<?php

declare(strict_types=1);

return [
    'title' => 'Intelligenza artificiale',
    'description' => 'L\'IA struttura il tuo CV, riassume il tuo GitHub ed estrae i requisiti delle offerte. Non inventa mai esperienze: rivedi tutto prima del salvataggio.',
    'status' => [
        'available' => 'L\'IA è disponibile.',
        'unavailable' => 'Al momento nessun provider di IA è disponibile. Puoi usare la tua chiave Anthropic.',
    ],
    'consent' => [
        'given' => 'Hai acconsentito al trattamento dei tuoi dati da parte dell\'IA.',
        'missing' => 'Non hai ancora acconsentito al trattamento dei tuoi dati da parte dell\'IA. Ti verrà chiesto quando userai una funzione di IA.',
        'manage' => 'Gestisci in Privacy',
    ],
    'usage' => [
        'title' => 'Utilizzo questo mese',
        'calls' => '{0} Nessuna chiamata|{1} :count chiamata|[2,*] :count chiamate',
        'budget' => 'Budget incluso: :used di :limit USD',
        'own_key_cost' => 'Costo sulla tua chiave: :cost USD (fatturato direttamente da Anthropic).',
        'tasks' => [
            'extract_job_requirements' => 'Requisiti dell\'offerta',
            'structure_cv' => 'Strutturazione del CV',
            'summarize_github' => 'Riepilogo GitHub',
            'tailor_cv' => 'Adattamento del CV',
            'cover_letter' => 'Lettera di presentazione',
        ],
    ],
    'key' => [
        'title' => 'La tua chiave Anthropic (facoltativa)',
        'description' => 'Con la tua chiave, le tue chiamate non usano il budget incluso. Viene salvata cifrata e non viene mai più mostrata.',
        'label' => 'Chiave API',
        'placeholder' => 'sk-ant-…',
        'current' => 'Chiave salvata: :hint',
        'save' => 'Salva la chiave',
        'remove' => 'Rimuovi la chiave',
        'remove_confirm' => 'Rimuovere la tua chiave Anthropic?',
    ],
    'key_saved' => 'Chiave salvata.',
    'key_removed' => 'Chiave rimossa.',
    'errors' => [
        'disabled' => 'L\'IA è disattivata.',
        'no_provider' => 'Nessun provider di IA è configurato.',
        'budget_exceeded' => 'Hai esaurito il budget di IA di questo mese. Puoi aggiungere la tua chiave Anthropic in Impostazioni › IA.',
        'rate_limited' => 'Troppe richieste di IA. Attendi un momento.',
        'failed' => 'I provider di IA non hanno risposto correttamente. Riprova più tardi.',
    ],
];
