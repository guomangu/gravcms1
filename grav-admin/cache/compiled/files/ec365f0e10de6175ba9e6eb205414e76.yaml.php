<?php
return [
    '@class' => 'Grav\\Common\\File\\CompiledYamlFile',
    'filename' => '/home/gu/g2/grav-admin/user/blueprints/flex-objects/knowledge-tags.yaml',
    'modified' => 1768774921,
    'size' => 2034,
    'data' => [
        'title' => 'Tags de Connaissance',
        'description' => 'Taxonomie des connaissances.',
        'type' => 'flex-objects',
        'config' => [
            'admin' => [
                'router' => [
                    'path' => '/flex-objects/knowledge-tags'
                ],
                'list' => [
                    'title' => 'name',
                    'fields' => [
                        'name' => [
                            'link' => 'edit'
                        ],
                        'slug' => NULL,
                        'description' => NULL
                    ]
                ],
                'menu' => [
                    'list' => [
                        'route' => '/flex-objects/knowledge-tags',
                        'title' => 'Tags',
                        'icon' => 'fa-tags'
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
                        'filename' => 'tags.json'
                    ]
                ]
            ]
        ],
        'form' => [
            'validation' => 'loose',
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'label' => 'Nom du Tag',
                    'validate' => [
                        'required' => true
                    ]
                ],
                'slug' => [
                    'type' => 'text',
                    'label' => 'Identifiant'
                ],
                'description' => [
                    'type' => 'textarea',
                    'label' => 'Description',
                    'rows' => 3
                ],
                'tag_type' => [
                    'type' => 'select',
                    'label' => 'Type de Tag',
                    'options' => [
                        'global' => 'Global / Racine',
                        'pays' => 'Pays',
                        'region' => 'Région / Département',
                        'ville' => 'Ville',
                        'rue' => 'Rue / Toponyme',
                        'numero' => 'Numéro'
                    ],
                    'validate' => [
                        'required' => true
                    ]
                ],
                'parent' => [
                    'type' => 'selectize',
                    'label' => 'Tag Parent',
                    'help' => 'Hiérarchie: Région > Ville > Rue > Numéro',
                    'validate' => [
                        'type' => 'string'
                    ],
                    'data-options@' => '\\Grav\\Plugin\\SocialCorePlugin::getKnowledgeTagsOptions'
                ],
                'latitude' => [
                    'type' => 'text',
                    'label' => 'Latitude',
                    'validate' => [
                        'type' => 'number'
                    ]
                ],
                'longitude' => [
                    'type' => 'text',
                    'label' => 'Longitude',
                    'validate' => [
                        'type' => 'number'
                    ]
                ],
                'postcode' => [
                    'type' => 'text',
                    'label' => 'Code Postal'
                ],
                'citycode' => [
                    'type' => 'text',
                    'label' => 'Code INSEE (Commune)'
                ],
                'context' => [
                    'type' => 'text',
                    'label' => 'Contexte (Département/Région texte)'
                ]
            ]
        ]
    ]
];
