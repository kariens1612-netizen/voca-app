<?php

return [
    'image_max_kb' => (int) env('INSPIRATION_IMAGE_MAX_KB', 5120),
    'pdf_max_kb' => (int) env('INSPIRATION_PDF_MAX_KB', 20480),
    'video_max_kb' => (int) env('INSPIRATION_VIDEO_MAX_KB', 102400),
];
