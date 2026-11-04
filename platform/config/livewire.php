<?php

return [
    'payload' => [
        // Structured text-editor lists can exceed the default nesting depth.
        'max_size' => 1024 * 1024,
        'max_nesting_depth' => 32,
        'max_calls' => 50,
        'max_components' => 200,
    ],
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        // Transport ceiling only. CreatorProofVideo enforces the admin's lower current limit.
        'rules' => ['required', 'file', 'max:102400'],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => ['png', 'gif', 'bmp', 'jpg', 'jpeg', 'webp'],
        'max_upload_time' => 10,
        'cleanup' => true,
    ],
];
