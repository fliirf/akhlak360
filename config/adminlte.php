<?php

use JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter;
use JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter;

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title' => 'AKHLAK 360',
    'title_prefix' => '',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'logo' => '<b>AKHLAK</b>360',
    'logo_img' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Admin Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo' => [
        'enabled' => false,
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'Auth Logo',
            'class' => '',
            'width' => 50,
            'height' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader' => [
        'enabled' => true,
        'mode' => 'fullscreen',
        'img' => [
            'path' => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt' => 'AKHLAK 360 Preloader Image',
            'effect' => 'animation__shake',
            'width' => 60,
            'height' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled' => true,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => false,
    'usermenu_profile_url' => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => null,
    'layout_fixed_navbar' => null,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => 'card-outline card-primary',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-dark-primary elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav' => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url' => true,
    'dashboard_url' => 'dashboard',
    'logout_url' => 'logout',
    'login_url' => 'sso.login',
    'register_url' => null,
    'password_reset_url' => null,
    'password_email_url' => null,
    'profile_url' => 'profile.edit',
    'disable_darkmode_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'menu' => [
        // Navbar items:
        [
            'type' => 'navbar-search',
            'text' => 'search',
            'topnav_right' => true,
        ],
        [
            'type' => 'fullscreen-widget',
            'topnav_right' => true,
        ],
        [
            'type' => 'navbar-notification',
            'id' => 'navbar-notifications',
            'icon' => 'far fa-bell',
            'label' => 0,
            'label_color' => 'secondary',
            'icon_color' => 'muted',
            'route' => 'notifications.index',
            'topnav_right' => true,
            'dropdown_mode' => true,
            'dropdown_flabel' => 'View all notifications',
            'update_cfg' => [
                'route' => 'notifications.navbar',
                'period' => 30,
            ],
            'can' => 'view-notifications',
        ],

        // Sidebar items:
        [
            'type' => 'sidebar-menu-search',
            'text' => 'search',
        ],
        ['header' => 'AKHLAK 360'],
        [
            'text' => 'Dashboard',
            'route' => 'dashboard',
            'icon' => 'fas fa-fw fa-tachometer-alt',
            'active' => [
                'admin/dashboard',
                'supervisor/dashboard',
                'employee/dashboard',
                'management/dashboard',
                'it/dashboard',
            ],
        ],
        [
            'text' => 'Master Data',
            'icon' => 'fas fa-fw fa-database',
            'can' => 'view-master-data-menu',
            'active' => ['master-data/*'],
            'submenu' => [
                [
                    'text' => 'Departments',
                    'route' => 'master-data.departments.index',
                    'icon' => 'fas fa-fw fa-sitemap',
                    'can' => 'view-master-data',
                ],
                [
                    'text' => 'Positions',
                    'route' => 'master-data.positions.index',
                    'icon' => 'fas fa-fw fa-briefcase',
                    'can' => 'view-master-data',
                ],
                [
                    'text' => 'Employees',
                    'route' => 'master-data.employees.index',
                    'icon' => 'fas fa-fw fa-id-badge',
                    'can' => 'view-master-data',
                ],
                [
                    'text' => 'User & Role',
                    'route' => 'master-data.users.index',
                    'icon' => 'fas fa-fw fa-user-shield',
                    'can' => 'view-master-data',
                ],
                [
                    'text' => 'HRIS Sync',
                    'route' => 'master-data.hris-sync.index',
                    'icon' => 'fas fa-fw fa-sync-alt',
                    'can' => 'view-hris-sync',
                ],
            ],
        ],
        [
            'text' => 'Assessment Cycle',
            'icon' => 'far fa-fw fa-calendar-alt',
            'can' => 'view-assessment-cycle',
            'active' => ['assessment-cycle/*'],
            'submenu' => [
                [
                    'text' => 'Periods',
                    'route' => 'assessment-cycle.periods.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'manage-assessment-cycle',
                ],
                [
                    'text' => 'Weights',
                    'route' => 'assessment-cycle.weights.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'manage-assessment-cycle',
                ],
                [
                    'text' => 'Peer Approval',
                    'route' => 'assessment-cycle.peer-approval.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-peer-approval',
                ],
                [
                    'text' => 'Assign Assessors',
                    'route' => 'assessment-cycle.assign-assessors.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'assign-assessors',
                ],
            ],
        ],
        [
            'text' => 'Assessment',
            'icon' => 'fas fa-fw fa-clipboard-check',
            'can' => 'view-assessment',
            'active' => ['assessment/*'],
            'submenu' => [
                [
                    'text' => 'Pending Assessments',
                    'route' => 'assessment.pending.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Fill Assessment',
                    'route' => 'assessment.fill.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Team Results',
                    'route' => 'assessment.results.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-team-results',
                ],
                [
                    'text' => 'Personal Results',
                    'route' => 'assessment.results.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-personal-results',
                ],
            ],
        ],
        [
            'text' => 'Analytics',
            'icon' => 'fas fa-fw fa-chart-line',
            'can' => 'view-analytics',
            'active' => ['analytics/*'],
            'submenu' => [
                [
                    'text' => 'Core Value Dashboard',
                    'route' => 'analytics.core-values.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-core-value-dashboard',
                ],
                [
                    'text' => 'Gap Analysis',
                    'route' => 'analytics.gap-analysis.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Department Distribution',
                    'route' => 'analytics.department-distribution.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Semester Trend',
                    'route' => 'analytics.semester-trend.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Below Threshold',
                    'route' => 'analytics.below-threshold.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
            ],
        ],
        [
            'text' => 'IDP & Talent',
            'icon' => 'fas fa-fw fa-user-graduate',
            'can' => 'view-idp',
            'active' => ['idp-talent/*'],
            'submenu' => [
                [
                    'text' => 'Team IDP',
                    'route' => 'idp-talent.idp-recommendations.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-team-idp',
                ],
                [
                    'text' => 'Personal IDP',
                    'route' => 'idp-talent.idp-recommendations.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-personal-idp',
                ],
                [
                    'text' => 'IDP Recommendations',
                    'route' => 'idp-talent.idp-recommendations.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-report-export',
                ],
                [
                    'text' => 'Talent Mapping',
                    'route' => 'idp-talent.talent-mapping.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-report-export',
                ],
            ],
        ],
        [
            'text' => 'Reports',
            'icon' => 'fas fa-fw fa-file-export',
            'can' => 'view-report-history',
            'active' => ['reports/*'],
            'submenu' => [
                [
                    'text' => 'Export Reports',
                    'route' => 'reports.export.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-report-export',
                ],
                [
                    'text' => 'Export History',
                    'route' => 'reports.history.index',
                    'icon' => 'far fa-fw fa-circle',
                    'can' => 'view-report-history',
                ],
            ],
        ],
        [
            'text' => 'Notifications',
            'route' => 'notifications.index',
            'icon' => 'fas fa-fw fa-bell',
            'can' => 'view-notifications',
            'active' => ['notifications'],
        ],
        [
            'text' => 'Audit & Compliance',
            'icon' => 'fas fa-fw fa-shield-alt',
            'can' => 'view-audit-compliance',
            'active' => ['audit-compliance/*'],
            'submenu' => [
                [
                    'text' => 'Audit Logs',
                    'route' => 'audit-compliance.audit-logs.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
                [
                    'text' => 'Compliance Monitoring',
                    'route' => 'audit-compliance.compliance-monitoring.index',
                    'icon' => 'far fa-fw fa-circle',
                ],
            ],
        ],
        [
            'text' => 'System Settings',
            'route' => 'system-settings.index',
            'icon' => 'fas fa-fw fa-cogs',
            'can' => 'view-system-settings',
            'active' => ['system-settings'],
        ],
        ['header' => 'ACCOUNT'],
        [
            'text' => 'Profil Saya',
            'route' => 'profile.edit',
            'icon' => 'fas fa-fw fa-user-cog',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters' => [
        GateFilter::class,
        HrefFilter::class,
        SearchFilter::class,
        ActiveFilter::class,
        ClassesFilter::class,
        LangFilter::class,
        DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/chart.js/chart.umd.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire' => false,
];
