<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates the English text of a message into Dutch (Flemish), French and
 * Spanish with Claude (Anthropic API, key ANTHROPIC_API_KEY). Without a key or
 * when the service fails nothing is translated and the English text is used.
 */
class Translator
{
    public const URL = 'https://api.anthropic.com/v1/messages';

    public const TARGETS = ['nl' => 'Dutch as written in Flanders (Belgium)', 'fr' => 'French as written in Belgium', 'es' => 'Spanish (Spain)'];

    public static function available(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  array<string, string>  $texts  field => English text
     * @return array<string, array<string, string>>|null locale => field => text, or null when not available
     */
    public function translate(array $texts): ?array
    {
        $texts = array_filter($texts, fn ($t) => trim((string) $t) !== '');
        if (! self::available() || $texts === []) {
            return null;
        }
        $prompt = "You translate short marketing and customer messages for CLUK, an event equipment rental company in Belgium.\n"
            .'Translate the JSON values below from English into each target language. Keep the tone (warm, short, professional), keep line breaks, '
            ."keep names, brand names, prices, dates, emoji and placeholders like {name} exactly as they are. Use the informal 'je' in Dutch, 'vous' in French and 'tú' in Spanish.\n"
            .'Targets: '.json_encode(self::TARGETS)."\n"
            ."Answer with JSON only, in this shape: {\"nl\": {...same keys...}, \"fr\": {...}, \"es\": {...}}\n\n"
            .json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        try {
            $res = Http::timeout(40)->withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post(self::URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 4000,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
            if (! $res->successful()) {
                Log::warning('Translation failed', ['http' => $res->status(), 'error' => mb_substr((string) $res->json('error.message'), 0, 200)]);

                return null;
            }
            $out = (string) collect($res->json('content', []))->where('type', 'text')->pluck('text')->implode('');
            $json = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', $out)), true);
            if (! is_array($json)) {
                Log::warning('Translation answer was not JSON');

                return null;
            }
            $result = [];
            foreach (array_keys(self::TARGETS) as $loc) {
                foreach (array_keys($texts) as $field) {
                    $v = $json[$loc][$field] ?? null;
                    if (is_string($v) && trim($v) !== '') {
                        $result[$loc][$field] = $v;
                    }
                }
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('Translation service not reachable', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }
}
