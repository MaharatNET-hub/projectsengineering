<?php

return [
    // largest supporting file accepted with a study (MB)
    'max_upload_mb' => (int) env('STUDIES_MAX_UPLOAD_MB', 50),

    // show the automated (preliminary) findings to the client right after submitting. When false the
    // client only sees the status until the engineer issues the review.
    'show_preliminary' => (bool) env('STUDIES_SHOW_PRELIMINARY', true),

    // seconds of PDF reading per request when pre-filling / cross-checking from the supporting file
    'step_seconds' => (float) env('STUDIES_STEP_SECONDS', 10),
];
