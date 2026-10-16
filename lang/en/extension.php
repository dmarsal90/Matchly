<?php

declare(strict_types=1);

return [
    'title' => 'Browser extension',
    'desc' => 'Fills job application forms with the data from your approved applications. The extension only reads; it never submits anything for you.',
    'tokens' => 'Access tokens',
    'name' => 'Token name',
    'ph' => 'e.g. My laptop',
    'create' => 'Create token',
    'none' => 'You have not created any token yet.',
    'last' => 'Last used: :date',
    'never' => 'Never used',
    'created' => 'Created :date',
    'revoke' => 'Revoke',
    'new_title' => 'Copy your token now',
    'new_hint' => 'It is only shown this once. Paste it into the extension together with the :app address.',
    'url' => ':app address',
    'steps' => 'Install the extension',
    'step1' => 'Open chrome://extensions, turn on developer mode and click “Load unpacked”.',
    'step2' => 'Pick the “extension” folder of the :app repository.',
    'step3' => 'Open the extension, paste the address and the token, and click “Save”.',
    'step4' => 'On a job application page, choose your approved application and click “Fill this page”. You review and submit.',
    'too_many' => 'You already have :max tokens. Revoke one to create another.',
    'invalid_token' => 'Invalid or revoked token.',
    'saved' => 'Token created.',
];
