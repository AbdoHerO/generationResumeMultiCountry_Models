<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'ResumeAtlas'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env_bool('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'key' => (string) env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    // Set when the app is served from a sub-folder (e.g. http://localhost/resume/public).
    'base_path' => rtrim((string) env('APP_BASE_PATH', ''), '/'),
    'storage_path' => env('STORAGE_PATH', ''),
    'auto_migrate' => env_bool('AUTO_MIGRATE', true),
    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1,::1'),

    'session' => [
        'name' => 'resumeatlas_session',
        'secure' => env('SESSION_SECURE', 'auto'),
        'lifetime_minutes' => (int) env('SESSION_LIFETIME_MINUTES', '120'),
    ],

    // Bootstrap administrator, created once by AdminSeeder. The password is hashed and must be
    // changed at the first sign-in. Set ADMIN_INITIAL_PASSWORD in production before the first boot.
    'admin' => [
        'email' => strtolower((string) env('ADMIN_EMAIL', 'admin@abdoucloud.com')),
        'initial_password' => (string) env('ADMIN_INITIAL_PASSWORD', '123456789'),
        'name' => 'Administrator',
    ],

    'limits' => [
        'cvs_per_user' => 50,
        'photo_max_bytes' => 2 * 1024 * 1024,
        'content_max_bytes' => 400 * 1024,
        'login_max_attempts' => 5,
        'login_window_minutes' => 15,
        'register_per_hour' => 10,
    ],

    // CV import with an LLM through Groq's OpenAI-compatible chat API. The API token is set by an
    // administrator (stored encrypted in the database); GROQ_API_KEY is only a fallback.
    // A token mistakenly placed in GROQ_API_URL is used as the fallback key, with the default URL.
    'ai' => [
        'url' => preg_match('#^https?://#i', (string) env('GROQ_API_URL', '')) ? (string) env('GROQ_API_URL') : 'https://api.groq.com/openai/v1/chat/completions',
        'model' => (string) env('GROQ_MODEL', 'qwen/qwen3.8-27b'),
        'env_key' => (string) (env('GROQ_API_KEY') ?? (preg_match('#^gsk_[A-Za-z0-9]+$#', (string) env('GROQ_API_URL', '')) ? env('GROQ_API_URL') : '')),
        // Seconds per API call: below the 60 s request limit of the web server.
        'timeout' => (int) env('GROQ_TIMEOUT', '50'),
        // Output tokens per request. Groq's free tier refuses more than 1000 for some models;
        // raise it (e.g. 8192) on a paid tier so imports need fewer, larger requests.
        'max_tokens' => (int) env('GROQ_MAX_TOKENS', '950'),
        'import_max_bytes' => 5 * 1024 * 1024,
        'import_max_chars' => 20000,
        // Seconds of work per import step request (each stays under the 60 s web server limit).
        'step_budget' => 30,
        'imports_per_hour' => 10,
    ],

    'mail' => [
        'mailer' => env('MAIL_MAILER', 'disabled'),
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', '587'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'ResumeAtlas'),
    ],
];
