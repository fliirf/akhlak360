<?php

return [
    'application_name' => env('AKHLAK360_APP_NAME', 'Sistem Penilaian 360° Core Values AKHLAK'),
    'default_threshold_score' => (float) env('AKHLAK360_DEFAULT_THRESHOLD', 3.00),
    'reminder_interval_days' => (int) env('AKHLAK360_REMINDER_INTERVAL_DAYS', 3),
    'email_notifications_enabled' => (bool) env('AKHLAK360_EMAIL_NOTIFICATIONS', true),
    'in_app_notifications_enabled' => (bool) env('AKHLAK360_IN_APP_NOTIFICATIONS', true),
    'role_mapping' => [
        'admin_hr_employee_numbers' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('ADMIN_HR_EMPLOYEE_NUMBERS', 'EMP001'))
        ))),
        'management_employee_numbers' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('MANAGEMENT_EMPLOYEE_NUMBERS', 'EMP002'))
        ))),
        'it_admin_employee_numbers' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('IT_ADMIN_EMPLOYEE_NUMBERS', 'EMP003'))
        ))),
    ],
    'supervisor_candidate_position_levels' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SUPERVISOR_CANDIDATE_POSITION_LEVELS', 'L3,L4,L5'))
    ))),
    'role_access_matrix' => [
        'admin_hr' => [
            'Dashboard Admin HR',
            'Master Data dan User & Role',
            'HRIS Sync',
            'Assessment Period dan Weight',
            'Peer Approval dan Assign Assessors',
            'Analytics',
            'IDP dan Talent Mapping',
            'Reports dan Export History',
            'Notifications',
            'Audit dan Compliance',
        ],
        'supervisor' => [
            'Dashboard Supervisor',
            'Peer Approval',
            'Pending dan Fill Assessment',
            'Team Results',
            'Team IDP',
            'Notifications',
        ],
        'employee' => [
            'Dashboard Employee',
            'Pending dan Fill Assessment',
            'Personal Results',
            'Personal IDP',
            'Notifications',
        ],
        'management' => [
            'Dashboard Management',
            'Analytics',
            'IDP dan Talent Mapping',
            'Reports dan Export History',
            'Notifications',
        ],
        'it_admin' => [
            'Dashboard IT',
            'HRIS Sync Logs',
            'Audit Logs dan Compliance Monitoring',
            'Export History',
            'System Settings',
            'Notifications',
        ],
    ],
    'default_weights' => [
        'supervisor' => 40,
        'peer' => 20,
        'subordinate' => 30,
        'self' => 10,
    ],
    'mvp' => [
        'hris_simulation' => true,
        'sso_simulation' => true,
        'database' => 'SQLite',
        'report_formats' => ['CSV', 'XLSX', 'PDF'],
    ],
];
