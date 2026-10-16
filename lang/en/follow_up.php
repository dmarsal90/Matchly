<?php

declare(strict_types=1);

return [
    'notification' => [
        'title' => 'Time to follow up?',
        'body' => ':company has not answered your application for :title in :days days.',
        'open' => 'Open my jobs',
    ],
    'mail' => [
        'subject' => 'Follow up with :company?',
        'heading' => 'No answer from :company yet',
        'intro' => 'You applied for :title on :date and have heard nothing since. A short follow-up often helps. Here is a draft you can adapt and send yourself:',
        'open' => 'Open my application',
        'reminder' => 'This is only a draft: we never contact employers for you.',
    ],
    'draft' => [
        'greeting' => ['formal' => 'Dear hiring team,', 'friendly' => 'Hi,', 'enthusiastic' => 'Hi there!'],
        'body' => 'I applied for the :title position at :company on :date and wanted to check how the process is going. I remain very interested in the role.',
        'body_second' => 'I wrote on :date about my application for the :title position at :company and have not heard back yet. I understand how busy things get and I am still very interested. Could you tell me where the process stands?',
        'thanks_body' => 'Thank you for taking the time to speak with me about the :title position at :company. I enjoyed learning more about the role and the team, and I remain very interested.',
        'thanks_close' => [
            'formal' => 'Please let me know if I can provide anything else. I look forward to the next steps.',
            'friendly' => 'Happy to send anything else you need. Thanks again!',
            'enthusiastic' => 'I would love to keep talking. Thank you so much!',
        ],
        'close' => [
            'formal' => 'Thank you for your time. I would be glad to provide any further information you need.',
            'friendly' => 'Happy to talk or send anything else you need. Thanks!',
            'enthusiastic' => 'I would love to hear where things stand. Thank you so much!',
        ],
        'sign_off' => ['formal' => 'Kind regards,', 'friendly' => 'Best,', 'enthusiastic' => 'Cheers!'],
    ],
];
