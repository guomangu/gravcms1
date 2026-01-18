<?php
return [
    '@class' => 'Grav\\Common\\File\\CompiledYamlFile',
    'filename' => '/home/gu/g2/grav-admin/user/config/plugins/flex-objects.yaml',
    'modified' => 1768678849,
    'size' => 440,
    'data' => [
        'enabled' => true,
        'built_in_css' => true,
        'directories' => [
            0 => 'blueprints://flex-objects/social-spaces.yaml',
            1 => 'blueprints://flex-objects/activity-stream.yaml',
            2 => 'blueprints://flex-objects/knowledge-tags.yaml',
            3 => 'blueprints://flex-objects/messages.yaml'
        ],
        'admin_list' => [
            'per_page' => 20,
            'order' => [
                'by' => 'updated_timestamp',
                'dir' => 'desc'
            ]
        ]
    ]
];
