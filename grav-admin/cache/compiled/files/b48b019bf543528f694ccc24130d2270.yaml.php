<?php
return [
    '@class' => 'Grav\\Common\\File\\CompiledYamlFile',
    'filename' => '/home/gu/g2/grav-admin/user/blueprints/flex-objects/activity-stream.yaml',
    'modified' => 1768689698,
    'size' => 1389,
    'data' => [
        'title' => 'Flux d\'Activité',
        'description' => 'Journal des activités du réseau social.',
        'type' => 'flex-objects',
        'config' => [
            'admin' => [
                'router' => [
                    'path' => '/flex-objects/activity-stream'
                ],
                'list' => [
                    'title' => 'actor',
                    'fields' => [
                        'timestamp' => NULL,
                        'actor' => NULL,
                        'verb' => NULL,
                        'object_type' => NULL,
                        'object_id' => NULL
                    ]
                ],
                'menu' => [
                    'list' => [
                        'route' => '/flex-objects/activity-stream',
                        'title' => 'Activité',
                        'icon' => 'fa-stream'
                    ]
                ]
            ],
            'data' => [
                'storage' => [
                    'class' => 'Grav\\Framework\\Flex\\Storage\\SimpleStorage',
                    'options' => [
                        'formatter' => [
                            'class' => 'Grav\\Framework\\File\\Formatter\\JsonFormatter'
                        ],
                        'folder' => 'user://data/flex-objects',
                        'filename' => 'activity.json'
                    ]
                ]
            ]
        ],
        'form' => [
            'validation' => 'loose',
            'fields' => [
                'timestamp' => [
                    'type' => 'text',
                    'label' => 'Horodatage'
                ],
                'actor' => [
                    'type' => 'text',
                    'label' => 'Acteur (Username)'
                ],
                'verb' => [
                    'type' => 'select',
                    'label' => 'Action',
                    'options' => [
                        'create' => 'A créé',
                        'update' => 'A modifié',
                        'join' => 'A rejoint',
                        'like' => 'A aimé',
                        'follow' => 'Suit'
                    ]
                ],
                'object_type' => [
                    'type' => 'select',
                    'label' => 'Type d\'objet',
                    'options' => [
                        'page' => 'Page Wiki',
                        'space' => 'Espace',
                        'user' => 'Utilisateur',
                        'message' => 'Message'
                    ]
                ],
                'object_id' => [
                    'type' => 'text',
                    'label' => 'ID de l\'objet'
                ],
                'context' => [
                    'type' => 'text',
                    'label' => 'Contexte'
                ]
            ]
        ]
    ]
];
