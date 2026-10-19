<?php

declare(strict_types=1);

/*
 * A stand-in for Groq's OpenAI-compatible chat API, for the HTTP tests (php -S router script).
 * - Accepts only the bearer token "gsk_test_token_0123456789abcdef".
 * - "You convert …" prompts: returns a structured CV built from the text it receives.
 * - "You translate …" prompts: returns every string prefixed with the target language, e.g. "[zh] ".
 */

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
header('Content-Type: application/json');
if ($auth !== 'Bearer gsk_test_token_0123456789abcdef') {
    http_response_code(401);
    echo json_encode(['error' => ['message' => 'Invalid API Key', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']]);
    return true;
}
$req = json_decode((string) file_get_contents('php://input'), true) ?: [];
$system = (string) ($req['messages'][0]['content'] ?? '');
$user = (string) ($req['messages'][1]['content'] ?? $req['messages'][0]['content'] ?? '');

$answer = 'pong';
if (str_starts_with($system, 'You convert')) {
    $name = preg_match('/^\s*([A-ZÀ-Ý][\p{L}-]+ [A-ZÀ-Ý][\p{L}-]+)/mu', preg_replace('/^.*?"""\n/s', '', $user), $m) ? $m[1] : '';
    $answer = json_encode(['sourceLanguage' => 'fr', 'content' => [
        'personal' => ['fullName' => $name, 'headline' => 'Développeuse Full-Stack', 'email' => 'camille.laurent@example.fr', 'city' => 'Lyon'],
        'sections' => [
            ['type' => 'summary', 'text' => 'Développeuse expérimentée.'],
            ['type' => 'experience', 'items' => [['title' => 'Développeuse PHP', 'organization' => 'Maison Verte', 'startDate' => '2021-03', 'current' => true, 'bullets' => ['Refonte du tunnel de commande']]]],
            ['type' => 'skills', 'items' => [['name' => 'PHP', 'category' => 'technical'], ['name' => 'Laravel', 'category' => 'technical']]],
            ['type' => 'languages', 'items' => [['name' => 'Français', 'level' => 'native']]],
        ],
    ]], JSON_UNESCAPED_UNICODE);
} elseif (str_starts_with($system, 'You translate')) {
    $to = str_contains($system, 'into Simplified Chinese') ? 'zh' : (str_contains($system, 'into English') ? 'en' : 'fr');
    $items = json_decode($user, true)['items'] ?? [];
    $answer = json_encode(['items' => array_map(fn ($s) => "[$to] $s", $items)], JSON_UNESCAPED_UNICODE);
}
echo json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $answer], 'finish_reason' => 'stop']]], JSON_UNESCAPED_UNICODE);
return true;
