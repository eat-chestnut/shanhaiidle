<?php

$defaultEnabled = in_array((string) env('APP_ENV', 'production'), ['local', 'development'], true);

return [
    'enabled' => (bool) env('GM_DEBUG_ENABLED', $defaultEnabled),
    'allowed_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => trim($email),
        explode(',', (string) env('GM_DEBUG_ALLOW_EMAILS', 'admin@example.com')),
    ))),
];
