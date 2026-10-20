<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Il est temps de relancer ?',
        'body' => ':company n\'a pas répondu à votre candidature pour :title depuis :days jours.',
        'open' => 'Ouvrir mes offres',
    ],
    'mail' => [
        'subject' => 'Relancer :company ?',
        'heading' => 'Pas encore de réponse de :company',
        'intro' => 'Vous avez postulé à :title le :date et n\'avez rien reçu depuis. Une courte relance aide souvent. Voici un brouillon que vous pouvez adapter et envoyer vous-même :',
        'open' => 'Ouvrir ma candidature',
        'reminder' => 'Ce n\'est qu\'un brouillon : nous ne contactons jamais les employeurs à votre place.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Madame, Monsieur,', 'friendly' => 'Bonjour,', 'enthusiastic' => 'Bonjour à toute l\'équipe !'],
        'body' => 'J\'ai postulé au poste de :title chez :company le :date et je souhaitais savoir où en est le processus. Ce poste continue de m\'intéresser vivement.',
        'body_second' => 'Je vous ai écrit le :date au sujet de ma candidature au poste de :title chez :company et je n\'ai pas encore reçu de réponse. Je comprends que vous soyez très occupés et je reste très intéressé(e). Pourriez-vous m\'indiquer où en est le processus ?',
        'thanks_body' => 'Je vous remercie d\'avoir pris le temps d\'échanger avec moi au sujet du poste de :title chez :company. J\'ai eu plaisir à en savoir plus sur le poste et sur l\'équipe, et je reste très intéressé(e).',
        'thanks_close' => [
            'formal' => 'N\'hésitez pas à me dire si je peux vous fournir autre chose. Je me tiens disponible pour les prochaines étapes.',
            'friendly' => 'Je peux vous envoyer tout ce dont vous avez besoin. Merci encore !',
            'enthusiastic' => 'Je serais ravi(e) de poursuivre la discussion. Merci beaucoup !',
        ],
        'close' => [
            'formal' => 'Je vous remercie du temps que vous m\'accorderez. Je me tiens à votre disposition pour tout complément d\'information.',
            'friendly' => 'Je reste disponible pour échanger ou vous envoyer tout ce dont vous avez besoin. Merci !',
            'enthusiastic' => 'J\'aimerais beaucoup savoir où en sont les choses. Merci beaucoup !',
        ],
        'sign_off' => ['formal' => 'Cordialement,', 'friendly' => 'Bien à vous,', 'enthusiastic' => 'À très bientôt !'],
    ],
];
