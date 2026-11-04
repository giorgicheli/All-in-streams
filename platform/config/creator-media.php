<?php

return [
    // Trusted deployment configuration only; never editable as executable paths in the admin UI.
    'ffprobe' => env('CREATOR_FFPROBE_BINARY', 'ffprobe'),
    'ffmpeg' => env('CREATOR_FFMPEG_BINARY', 'ffmpeg'),
    'worker_url' => env('CREATOR_MEDIA_WORKER_URL'),
    'worker_token' => env('CREATOR_MEDIA_WORKER_TOKEN'),
    // Deployment-owned request ceiling, leaving room for multipart overhead below the web-server limit.
    'request_max_mb' => (int) env('CREATOR_MEDIA_REQUEST_MAX_MB', 100),
];
