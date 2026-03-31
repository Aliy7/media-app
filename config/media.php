<?php

return [

    'thumbnail' => [
        'width'  => env('MEDIA_THUMBNAIL_WIDTH', 300),
        'height' => env('MEDIA_THUMBNAIL_HEIGHT', 300),
    ],

    'resize' => [
        'width'  => env('MEDIA_RESIZE_WIDTH', 1920),
        'height' => env('MEDIA_RESIZE_HEIGHT', 1080),
    ],

    // Seconds to pause at the start of each job so pipeline stages are visible
    // in the UI during demos. Set to 0 in production. Overridden to 0 in tests
    // via phpunit.xml so the test suite never sleeps.
    'step_delay' => (int) env('PROCESSING_STEP_DELAY', 0),

];
