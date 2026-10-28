<?php

return [
    // Registered extensions declare the modules they support, for example ['plugin' => ['modules' => ['pages']]].
    'extensions' => [],
    'route_modules' => [
        'access.login' => 'auth', 'access.password' => 'auth',
        'blog.settings' => 'settings', 'contact.settings' => 'settings', 'member.settings' => 'settings', 'slug.settings' => 'settings',
        'custom-fields' => 'field-groups', 'languages' => 'languages', 'settings.language' => 'languages', 'translations' => 'translations',
        'media' => 'media', 'theme.options' => 'theme-options', 'theme.custom-css' => 'theme-assets', 'theme.custom-js' => 'theme-assets', 'theme.custom-html' => 'theme-assets', 'theme.robots-txt' => 'theme-assets', 'theme' => 'themes', 'menus' => 'menus', 'widgets' => 'widgets',
        'settings' => 'settings', 'captcha.settings' => 'settings', 'social-login.settings' => 'settings', 'analytics.settings' => 'settings', 'optimize.settings' => 'settings', 'api.settings' => 'settings', 'api.sanctum-token' => 'operations', 'get-started' => 'operations', 'tools' => 'operations', 'data-synchronize' => 'operations', 'dashboard' => 'dashboard', 'notifications' => 'system', 'core.global-search' => 'system', 'system' => 'system', 'backups' => 'maintenance', 'audit-log' => 'maintenance', 'request-log' => 'maintenance', 'plugins' => 'maintenance',
        'pages' => 'pages', 'users' => 'users', 'roles' => 'roles',
        'posts' => 'posts', 'categories' => 'categories', 'tags' => 'tags', 'block' => 'blocks',
        'galleries' => 'galleries', 'member' => 'members', 'contacts.custom-fields' => 'contact-fields', 'contacts' => 'contacts',
    ],
    'supported_plugins' => [
        'pages' => [
            'analytics', 'audit-log', 'backup', 'block', 'blog', 'captcha',
            'language', 'language-advanced', 'translation', 'custom-field',
            'contact', 'cookie-consent', 'gallery', 'member', 'request-log', 'social-login',
        ],
    ],
];
