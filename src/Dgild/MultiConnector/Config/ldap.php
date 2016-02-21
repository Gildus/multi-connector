<?php

return [
    'plugins' => [
        'adldap' => [
            'account_suffix'=>  '@domain.local',
            'domain_controllers'=>  [
                'dc01.domain.local',
                'dc02.domain.local'
            ], // Load balancing domain controllers
            'base_dn'   =>  'DC=domain,DC=locall',
            'admin_username' => 'admin', // This is required for session persistance in the application
            'admin_password' => 'yourPassword',
        ],
        'fields' => [
            'mail', 'displayname' // 'department', 'telephonenumber'
        ],
    ],
];