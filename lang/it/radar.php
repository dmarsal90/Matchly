<?php

declare(strict_types=1);

return [
    'title' => 'Radar delle aziende',
    'description' => 'Segui le aziende e ti avvisiamo quando una loro nuova offerta compare tra quelle che raccogliamo. Non visitiamo i loro siti web: guardiamo solo le offerte che arrivano già nell\'app.',
    'list' => 'Aziende che segui',
    'empty' => 'Non segui ancora nessuna azienda. Clicca su “Segui l\'azienda” in qualsiasi offerta.',
    'since' => 'Dal :date',
    'last' => 'Ultimo avviso: :date',
    'never' => 'Ancora nessun avviso',
    'unfollow' => 'Non seguire più',
    'search' => 'Vedi le sue offerte',
    'follow' => 'Segui l\'azienda',
    'following' => 'Segui questa azienda',
    'manage' => 'Aziende che segui',
    'too_many' => 'Segui già :max aziende. Smetti di seguirne una per aggiungerne un\'altra.',
    'notification' => [
        'title' => 'Nuove offerte da un\'azienda che segui',
        'body' => '{1} :company ha :count nuova offerta: :titles|[2,*] :company ha :count nuove offerte: :titles',
    ],
    'mail' => [
        'subject' => '{1} :company ha una nuova offerta|[2,*] :company ha :count nuove offerte',
        'heading' => '{1} Una nuova offerta presso :company|[2,*] :count nuove offerte presso :company',
        'more' => '{1} E altra :count.|[2,*] E altre :count.',
        'why' => 'Ricevi questo messaggio perché segui :company.',
        'manage' => 'Gestisci le aziende che segui',
    ],
];
