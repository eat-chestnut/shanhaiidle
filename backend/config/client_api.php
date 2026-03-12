<?php

$defaultToken = in_array((string) env('APP_ENV', 'production'), ['local', 'development'], true)
    ? 'dev-client-token'
    : '';

return [
    'header_name' => 'X-Client-Token',
    'shared_token' => (string) env('CLIENT_API_SHARED_TOKEN', $defaultToken),
];
