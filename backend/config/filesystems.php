<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env(
        'FILESYSTEM_DISK',
        filter_var(env('GOOGLE_DRIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'google-drive' : 'local'
    ),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // S3-compatible object storage (MinIO, Wasabi, etc.) — same driver, path-style on
        's3-compatible' => [
            'driver' => 's3',
            'key' => env('S3_COMPAT_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('S3_COMPAT_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('S3_COMPAT_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('S3_COMPAT_BUCKET', env('AWS_BUCKET')),
            'url' => env('S3_COMPAT_URL', env('AWS_URL')),
            'endpoint' => env('S3_COMPAT_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('S3_COMPAT_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'report' => false,
        ],

        // Used by App\Services\GoogleDriveService (not a native Flysystem driver).
        // Legacy aliases: GOOGLE_DRIVE_ENABLED, GOOGLE_DRIVE_ROOT_FOLDER_ID, GOOGLE_DRIVE_SHARED_DRIVE_ID
        'google-drive' => [
            'driver' => 'local', // placeholder; uploads go through GoogleDriveService
            'root' => storage_path('app/google-drive-fallback'),
            'enabled' => filter_var(env('GOOGLE_DRIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', env('GOOGLE_DRIVE_ROOT_FOLDER_ID', 'root')),
            'shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'),
            'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
