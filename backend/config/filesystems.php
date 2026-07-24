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

    'default' => env('FILESYSTEM_DISK', 'local'),

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
            'url' => rtrim((string) env('APP_URL'), '/').'/storage',
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

        // FR-083 — QC defect-photo evidence. A PRIVATE, S3-compatible object
        // store (MinIO in development, per the owner decision). Never public,
        // never a permanent public URL: files are read only through short-lived
        // signed URLs after server-side authorisation (Rule 03 hard rules 13-14,
        // Rule 06 hard rules 16-17). There is deliberately no local-disk
        // production fallback — the driver is s3, always.
        'evidence' => [
            'driver' => 's3',
            'key' => env('EVIDENCE_S3_KEY'),
            'secret' => env('EVIDENCE_S3_SECRET'),
            'region' => env('EVIDENCE_S3_REGION', 'us-east-1'),
            'bucket' => env('EVIDENCE_S3_BUCKET'),
            'endpoint' => env('EVIDENCE_S3_ENDPOINT'),
            // MinIO and most S3-compatible stores require path-style addressing.
            'use_path_style_endpoint' => (bool) env('EVIDENCE_S3_PATH_STYLE', true),
            'visibility' => 'private',
            // Surface a storage failure rather than silently returning false: an
            // upload that did not persist must never be reported as stored.
            'throw' => true,
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
