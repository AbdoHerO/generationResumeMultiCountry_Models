<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use Tests\TestCase;

/**
 * Starts PHP's built-in server on the test database for end-to-end requests.
 * Set TEST_HTTP_PORT to change the port (default 8765).
 */
abstract class HttpTestCase extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    /** @var resource|null */
    private static $mock = null;
    protected static string $base = '';

    public static function setUpBeforeClass(): void
    {
        if (!TEST_DB_READY || self::$server !== null) {
            return;
        }
        $port = (int) (getenv('TEST_HTTP_PORT') ?: 8765);
        self::$base = "http://127.0.0.1:$port";
        // The AI service is a local mock (tests/Support/groq-mock.php): no network, no real token.
        $mockPort = $port + 1;
        $env = array_merge(getenv(), ['DB_DATABASE' => (string) config('database.database'), 'APP_ENV' => 'testing', 'AUTO_MIGRATE' => 'false', 'MAIL_MAILER' => 'disabled',
            'GROQ_API_URL' => "http://127.0.0.1:$mockPort/openai/v1/chat/completions", 'GROQ_MAX_TOKENS' => '950']);
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/public/index.php'];
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, BASE_PATH, $env);
        self::$mock = proc_open([PHP_BINARY, '-S', "127.0.0.1:$mockPort", BASE_PATH . '/tests/Support/groq-mock.php'], [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $mockPipes, BASE_PATH);
        for ($i = 0; $i < 50; $i++) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($fp) {
                fclose($fp);
                break;
            }
            usleep(100000);
        }
        register_shutdown_function(static function (): void {
            if (self::$server) {
                proc_terminate(self::$server);
                self::$server = null;
            }
            if (self::$mock) {
                proc_terminate(self::$mock);
                self::$mock = null;
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Throttling counters would otherwise accumulate across test runs from 127.0.0.1.
        Database::query('DELETE FROM login_attempts');
    }

    protected function client(): Client
    {
        return new Client(self::$base);
    }

    /** Registers and signs in a fresh user; returns the client and e-mail. @return array{0: Client, 1: string} */
    protected function newUser(string $name = 'Test User'): array
    {
        $c = $this->client();
        $c->get('/register');
        $email = 'u' . bin2hex(random_bytes(5)) . '@example.test';
        $r = $c->post('/register', ['name' => $name, 'email' => $email, 'password' => 'Correct-Horse-42', 'password_confirmation' => 'Correct-Horse-42', 'terms' => '1']);
        $this->assertSame(302, $r['status'], 'Registration redirects');
        $this->assertStringEndsWith('/dashboard', $r['location']);
        $c->get('/dashboard');
        return [$c, $email];
    }

    /** Creates a CV through the creation form and returns its id. */
    protected function createCv(Client $c, string $country = 'FR', string $language = 'fr', string $template = ''): int
    {
        $c->get('/cvs/new');
        $r = $c->post('/cvs', ['country' => $country, 'language' => $language, 'template' => $template]);
        $this->assertSame(302, $r['status']);
        $this->assertMatchesRegularExpression('#/cvs/(\d+)/edit$#', $r['location']);
        preg_match('#/cvs/(\d+)/edit$#', $r['location'], $m);
        return (int) $m[1];
    }

    /** @return array<string, mixed> the editor state embedded in the editor page */
    protected function editorState(Client $c, int $id): array
    {
        $r = $c->get("/cvs/$id/edit");
        $this->assertSame(200, $r['status']);
        $this->assertMatchesRegularExpression('#<script type="application/json" id="editor-state">(.*?)</script>#s', $r['body']);
        preg_match('#<script type="application/json" id="editor-state">(.*?)</script>#s', $r['body'], $m);
        return json_decode($m[1], true);
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    protected function savePayload(array $state): array
    {
        $cv = $state['cv'];
        return ['version' => $cv['version'], 'title' => $cv['title'], 'country' => $cv['country'], 'language' => $cv['language'],
            'templateId' => $cv['templateId'], 'content' => $cv['content'], 'design' => $cv['design']];
    }
}
