<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\Ai\AiException;
use App\Services\Ai\GroqClient;
use App\Services\Cv\SampleContent;
use App\Services\Profile\ProfileService;
use App\Services\Profile\ResumeImporter;
use App\Services\Secrets;
use Tests\TestCase;

/** PDF import helpers, the encrypted token and the Groq client's settings (no network). */
final class ImporterTest extends TestCase
{
    public function testTextIsSplitIntoPartsThatFitTheOutputLimit(): void
    {
        $text = implode("\n\n", array_fill(0, 30, str_repeat('Développement d’applications web. ', 6)));
        $parts = ResumeImporter::splitText($text, 950);
        $this->assertGreaterThan(1, count($parts));
        foreach ($parts as $part) {
            $this->assertLessThanOrEqual((950 * 0.85 - 120) / 1.9 + 1, ResumeImporter::estimateTokens($part));
        }
        $this->assertSame(mb_strlen(str_replace("\n", '', $text)), mb_strlen(str_replace("\n", '', implode('', $parts))), 'No text is lost');
        $this->assertCount(1, ResumeImporter::splitText($text, 100000), 'A large limit keeps the CV in one part');
    }

    public function testChineseTextCountsMoreTokensPerCharacter(): void
    {
        $this->assertGreaterThan(ResumeImporter::estimateTokens(str_repeat('a', 100)) * 3, ResumeImporter::estimateTokens(str_repeat('工', 100)));
    }

    public function testTranslationTouchesOnlyHumanReadableText(): void
    {
        $content = SampleContent::for('FR', 'fr');
        $entries = ResumeImporter::collectStrings($content);
        $texts = array_column($entries, 1);
        $this->assertContains($content['personal']['headline'], $texts);
        $this->assertNotContains($content['personal']['email'], $texts);
        $this->assertNotContains($content['personal']['fullName'], $texts, 'Names are not translated');
        foreach ($texts as $t) {
            $this->assertDoesNotMatchRegularExpression('/^\d{4}(-\d{2})?$/', $t, 'Dates are not sent');
        }
        $translated = array_map(fn ($t) => '[en] ' . $t, $texts);
        $out = ResumeImporter::applyStrings($content, $entries, $translated, 'en');
        $this->assertSame('[en] ' . $content['personal']['headline'], $out['personal']['headline']);
        $this->assertSame($content['personal']['email'], $out['personal']['email']);
        $this->assertSame(array_column($content['sections'], 'type'), array_column($out['sections'], 'type'));
        $exp = $out['sections'][1]['items'][0];
        $this->assertSame($content['sections'][1]['items'][0]['startDate'], $exp['startDate']);
        $this->assertStringStartsWith('[en] ', $exp['bullets'][0]);
    }

    public function testLongTextsAreSplitAndJoinedBack(): void
    {
        $content = ProfileService::emptyContent();
        $content['sections'][0]['text'] = implode(' ', array_fill(0, 60, 'Une phrase assez longue pour remplir la limite.'));
        $entries = ResumeImporter::collectStrings($content, 600);
        $this->assertGreaterThan(1, count($entries));
        $out = ResumeImporter::applyStrings($content, $entries, array_column($entries, 1), 'fr');
        $this->assertSame(preg_replace('/\s+/', ' ', $content['sections'][0]['text']), preg_replace('/\s+/', ' ', $out['sections'][0]['text']));
        foreach (ResumeImporter::batches($entries, 'zh', 600) as $batch) {
            $this->assertNotEmpty($batch);
        }
    }

    public function testPartsAreMergedAndContinuedEntriesReattached(): void
    {
        $a = SampleContent::for('FR', 'fr');
        $b = ['personal' => ['fullName' => 'Other', 'website' => 'https://example.org'], 'sections' => [
            ['type' => 'experience', 'items' => [['title' => '', 'bullets' => ['Suite de la mission']]]],
            ['type' => 'awards', 'items' => [['title' => 'Prix']]],
        ]];
        $m = ResumeImporter::merge([$a, $b]);
        $this->assertSame($a['personal']['fullName'], $m['personal']['fullName'], 'The first value wins');
        $this->assertSame('https://example.org', $m['personal']['website'], 'Missing values are filled');
        $exp = array_values(array_filter($m['sections'], fn ($s) => $s['type'] === 'experience'))[0]['items'];
        $this->assertCount(count($a['sections'][1]['items']), $exp, 'The continued entry is not a new one');
        $this->assertContains('Suite de la mission', end($exp)['bullets']);
        $this->assertContains('awards', array_column($m['sections'], 'type'));
    }

    public function testSecretsAreEncryptedAndMasked(): void
    {
        $token = 'gsk_example_' . bin2hex(random_bytes(12));
        $stored = Secrets::encrypt($token);
        $this->assertStringNotContainsString($token, $stored);
        $this->assertSame($token, Secrets::decrypt($stored));
        $this->assertNull(Secrets::decrypt('v1:' . base64_encode(random_bytes(60))), 'Altered values are refused');
        $this->assertSame('gsk_…' . substr($token, -4), Secrets::mask($token));
    }

    public function testANewTokenIsUsedImmediately(): void
    {
        $old = Setting::get(GroqClient::SETTING_KEY);
        try {
            Setting::set(GroqClient::SETTING_KEY, Secrets::encrypt('gsk_first_token_aaaaaaaaaaaa'));
            $this->assertSame('gsk_first_token_aaaaaaaaaaaa', GroqClient::settings()['key']);
            // Written by another process (the admin's request): no cache flush in this one.
            \App\Core\Database::query('UPDATE settings SET value = ? WHERE name = ?', [json_encode(Secrets::encrypt('gsk_second_token_bbbbbbbbbbb')), GroqClient::SETTING_KEY]);
            $this->assertSame('gsk_second_token_bbbbbbbbbbb', GroqClient::settings()['key']);
            $this->assertSame('admin', GroqClient::settings()['keySource']);
        } finally {
            $old === null ? \App\Core\Database::query('DELETE FROM settings WHERE name = ?', [GroqClient::SETTING_KEY]) : Setting::set(GroqClient::SETTING_KEY, $old);
            Setting::flush();
        }
    }

    public function testLineReferencesAreRebuiltFromThePdfText(): void
    {
        $lines = ['Camille Laurent', 'Développeuse full-stack avec 6 ans', 'd’expérience dans le e-commerce.', '• Refonte du tunnel de commande :',
            'conversion +18 %.', '• Migration de l’API vers PHP 8.3.', '- Encadrement de deux juniors.'];
        $answer = ['content' => ['personal' => ['fullName' => 'Camille Laurent', 'headline' => 'L99'], 'sections' => [
            ['type' => 'summary', 'text' => 'L2-L3'],
            ['type' => 'experience', 'items' => [['title' => 'Dev', 'bullets' => ['L4-L7']]]],
            ['type' => 'custom', 'title' => 'X', 'items' => ['L6', 'Texte copié']],
        ]]];
        $r = ResumeImporter::resolveLineRefs($answer, $lines)['content'];
        $this->assertSame('Développeuse full-stack avec 6 ans d’expérience dans le e-commerce.', $r['sections'][0]['text'], 'Wrapped lines joined');
        $this->assertSame(['Refonte du tunnel de commande : conversion +18 %.', 'Migration de l’API vers PHP 8.3.', 'Encadrement de deux juniors.'],
            $r['sections'][1]['items'][0]['bullets'], 'A range is split at bullet marks, continuation lines joined');
        $this->assertSame(['Migration de l’API vers PHP 8.3.', 'Texte copié'], $r['sections'][2]['items'], 'Copied text is kept');
        $this->assertSame('Camille Laurent', $r['personal']['fullName']);
        $this->assertSame('', ResumeImporter::resolveLineRefs(['x' => 'L99'], $lines)['x']);
    }

    public function testPartsEndBeforeAHeadingNotInsideAnEntry(): void
    {
        $lines = [];
        for ($i = 0; $i < 40; $i++) {
            $lines[] = 'Réalisation numéro ' . $i . ' avec un texte assez long pour remplir la partie rapidement.';
        }
        $lines[12] = 'PROJETS';
        $parts = ResumeImporter::splitLines($lines, 700);
        $this->assertGreaterThan(1, count($parts));
        $this->assertSame(range(0, 39), array_merge(...$parts), 'Every line once, in order');
        $this->assertSame(12, $parts[1][0], 'The first part ends just before the heading, not at the size limit');
        $this->assertSame(['Camille', 'Lyon'], ResumeImporter::lines(" Camille \n\n  Lyon  "));
    }

    public function testRateLimitDurationsAndJsonAnswers(): void
    {
        $this->assertSame(7.0, GroqClient::seconds('7'));
        $this->assertSame(53.16, GroqClient::seconds('53.16s'));
        $this->assertSame(62.5, GroqClient::seconds('1m2.5s'));
        $this->assertSame(['a' => 1], GroqClient::decodeJson("Sure:\n```json\n{\"a\": 1}\n```"));
        $this->expectException(AiException::class);
        GroqClient::decodeJson('no json here');
    }
}
