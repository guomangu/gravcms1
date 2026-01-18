<?php
return [
    '@class' => 'Grav\\Common\\File\\CompiledYamlFile',
    'filename' => '/home/gu/g2/grav-admin/user/config/site.yaml',
    'modified' => 1768776992,
    'size' => 335,
    'data' => [
        'title' => 'Social Knowledge Network',
        'default_lang' => 'fr',
        'author' => [
            'name' => 'SKN Team',
            'email' => 'contact@skn.local'
        ],
        'metadata' => [
            'description' => 'Réseau social de partage de connaissances'
        ],
        'routes' => [
            '/room/(.*)' => '/sys/room',
            '/profile/(.*)' => '/sys/profiles/dispatcher',
            '/tags/(.*)' => '/sys/tags/dispatcher',
            '/mesrooms' => '/mesrooms'
        ]
    ]
];
