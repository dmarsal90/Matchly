<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'È ora di fare un sollecito?',
        'body' => ':company non ha risposto alla tua candidatura per :title da :days giorni.',
        'open' => 'Apri le mie offerte',
    ],
    'mail' => [
        'subject' => 'Sollecitare :company?',
        'heading' => 'Ancora nessuna risposta da :company',
        'intro' => 'Ti sei candidato per :title il :date e da allora non hai ricevuto nulla. Un breve sollecito spesso aiuta. Ecco una bozza che puoi adattare e inviare tu stesso:',
        'open' => 'Apri la mia candidatura',
        'reminder' => 'Questa è solo una bozza: non contattiamo mai i datori di lavoro al tuo posto.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Gentile team di selezione,', 'friendly' => 'Buongiorno,', 'enthusiastic' => 'Ciao a tutti!'],
        'body' => 'Mi sono candidato/a per la posizione di :title presso :company il :date e vorrei sapere a che punto è il processo. Resto molto interessato/a al ruolo.',
        'body_second' => 'Il :date vi ho scritto riguardo alla mia candidatura per la posizione di :title presso :company e non ho ancora ricevuto risposta. Capisco quanto si possa essere impegnati e sono ancora molto interessato/a. Potreste dirmi a che punto è il processo?',
        'thanks_body' => 'Grazie per il tempo che mi avete dedicato parlando della posizione di :title presso :company. Mi è piaciuto conoscere meglio il ruolo e il team e resto molto interessato/a.',
        'thanks_close' => [
            'formal' => 'Fatemi sapere se posso fornire altro. Resto in attesa dei prossimi passi.',
            'friendly' => 'Posso inviare volentieri qualsiasi altra cosa vi serva. Grazie ancora!',
            'enthusiastic' => 'Mi farebbe molto piacere continuare a parlarne. Grazie mille!',
        ],
        'close' => [
            'formal' => 'Vi ringrazio per il tempo dedicato. Sarò lieto/a di fornire qualsiasi ulteriore informazione.',
            'friendly' => 'Sono disponibile a parlarne o a inviarvi qualsiasi altra cosa vi serva. Grazie!',
            'enthusiastic' => 'Mi piacerebbe molto sapere a che punto siamo. Grazie mille!',
        ],
        'sign_off' => ['formal' => 'Cordiali saluti,', 'friendly' => 'Un saluto,', 'enthusiastic' => 'A presto!'],
    ],
];
