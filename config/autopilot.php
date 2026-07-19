<?php

return [
    'po_draft_enabled' => filter_var(env('AUTOPILOT_PO_DRAFT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'minimum_confidence' => (float) env('AUTOPILOT_MINIMUM_CONFIDENCE', 0.75),
    'scan_dedupe_minutes' => (int) env('AUTOPILOT_SCAN_DEDUPE_MINUTES', 30),
    'real_email_enabled' => filter_var(env('REAL_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
