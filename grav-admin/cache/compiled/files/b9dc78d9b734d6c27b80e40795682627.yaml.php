<?php
return [
    '@class' => 'Grav\\Common\\File\\CompiledYamlFile',
    'filename' => '/home/gu/g2/grav-admin/user/config/plugins/login.yaml',
    'modified' => 1768686114,
    'size' => 1359,
    'data' => [
        'enabled' => true,
        'built_in_css' => false,
        'redirect_to_login' => true,
        'redirect_after_login' => true,
        'redirect_after_logout' => true,
        'route' => '/login',
        'route_after_login' => '/',
        'route_after_logout' => '/',
        'route_activate' => '/activate',
        'route_forgot' => '/forgot',
        'route_reset' => '/reset',
        'route_profile' => '/profile',
        'route_register' => '/register',
        'route_unauthorized' => '/login',
        'twofa_enabled' => false,
        'dynamic_page_visibility' => true,
        'parent_acl' => false,
        'protect_protected_page_media' => false,
        'rememberme' => [
            'enabled' => true,
            'timeout' => 604800,
            'name' => 'skn-rememberme'
        ],
        'max_pw_resets_count' => 3,
        'max_pw_resets_interval' => 60,
        'max_login_count' => 5,
        'max_login_interval' => 10,
        'ipv6_subnet_size' => 64,
        'user_registration' => [
            'enabled' => true,
            'fields' => [
                0 => 'username',
                1 => 'password',
                2 => 'email',
                3 => 'fullname'
            ],
            'default_values' => [
                'title' => 'Membre'
            ],
            'access' => [
                'site' => [
                    'login' => true
                ]
            ],
            'redirect_after_registration' => '/',
            'redirect_after_activation' => '/login',
            'options' => [
                'validate_password1_and_password2' => true,
                'set_user_disabled' => false,
                'login_after_registration' => true,
                'send_activation_email' => false,
                'manually_enable' => false,
                'send_notification_email' => false,
                'send_welcome_email' => false,
                'user_needs_state' => true
            ]
        ]
    ]
];
