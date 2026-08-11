<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads Configuration
    |--------------------------------------------------------------------------
    |
    | Set disk explicitly to 'local' so Livewire temporary file uploads always
    | read and write to storage/app/livewire-tmp consistently.
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a', 'jpg', 'jpeg', 'mpga',
            'webp', 'wma', 'docx', 'xlsx', 'csv', 'pdf',
        ],
        'max_upload_time' => 5,
    ],

];
