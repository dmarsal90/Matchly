<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Zeit für eine Nachfrage?',
        'body' => ':company hat seit :days Tagen nicht auf deine Bewerbung als :title geantwortet.',
        'open' => 'Meine Stellen öffnen',
    ],
    'mail' => [
        'subject' => 'Bei :company nachfragen?',
        'heading' => 'Noch keine Antwort von :company',
        'intro' => 'Du hast dich am :date auf :title beworben und seither nichts gehört. Eine kurze Nachfrage hilft oft. Hier ist ein Entwurf, den du anpassen und selbst senden kannst:',
        'open' => 'Meine Bewerbung öffnen',
        'reminder' => 'Dies ist nur ein Entwurf: Wir kontaktieren nie Arbeitgeber für dich.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Sehr geehrtes Recruiting-Team,', 'friendly' => 'Hallo,', 'enthusiastic' => 'Hallo zusammen!'],
        'body' => 'Ich habe mich am :date auf die Stelle als :title bei :company beworben und wollte nachfragen, wie weit der Prozess ist. Ich bin weiterhin sehr an der Stelle interessiert.',
        'body_second' => 'Ich habe Ihnen am :date wegen meiner Bewerbung auf die Stelle als :title bei :company geschrieben und noch keine Antwort erhalten. Ich weiß, wie viel gerade los ist, und bin weiterhin sehr interessiert. Könnten Sie mir sagen, wie der Stand des Prozesses ist?',
        'thanks_body' => 'Vielen Dank, dass Sie sich die Zeit genommen haben, mit mir über die Stelle als :title bei :company zu sprechen. Es hat mir gefallen, mehr über die Aufgabe und das Team zu erfahren, und ich bin weiterhin sehr interessiert.',
        'thanks_close' => [
            'formal' => 'Bitte sagen Sie mir, wenn ich noch etwas bereitstellen kann. Ich freue mich auf die nächsten Schritte.',
            'friendly' => 'Gern schicke ich alles Weitere, was Sie brauchen. Nochmals vielen Dank!',
            'enthusiastic' => 'Ich würde mich freuen, das Gespräch fortzusetzen. Vielen Dank!',
        ],
        'close' => [
            'formal' => 'Vielen Dank für Ihre Zeit. Gern stelle ich Ihnen weitere Informationen zur Verfügung.',
            'friendly' => 'Gern spreche ich mit Ihnen oder schicke alles, was Sie brauchen. Danke!',
            'enthusiastic' => 'Ich würde sehr gern erfahren, wie der Stand ist. Vielen Dank!',
        ],
        'sign_off' => ['formal' => 'Mit freundlichen Grüßen', 'friendly' => 'Viele Grüße', 'enthusiastic' => 'Bis bald!'],
    ],
];
