<?php

namespace App\Services\AI\Concerns;

use Illuminate\Support\Str;

trait DecodesAiJsonResponse
{
  protected function normalizeMessageContent(mixed $content): string
    {
        if (is_array($content)) {
            return collect($content)
                ->map(function ($part) {
                    if (is_string($part)) {
                        return $part;
                    }
                    if (is_array($part)) {
                        return $part['text'] ?? $part['content'] ?? '';
                    }

                    return '';
                })
                ->filter()
                ->implode("\n");
        }

        return trim(is_string($content) ? $content : json_encode($content));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeJsonResponse(string $content): ?array
    {
        $rawCandidates = array_filter([
            trim($content),
            $this->extractJsonFromMarkdown($content),
            $this->extractBalancedJsonObject($content),
            $this->extractJsonObject($content),
        ]);

        $candidates = [];
        foreach ($rawCandidates as $candidate) {
            $candidates[] = $candidate;
            $candidates[] = $this->repairJson($candidate);
            $closed = $this->attemptCloseTruncatedJson($candidate);
            if ($closed !== null) {
                $candidates[] = $closed;
                $candidates[] = $this->repairJson($closed);
            }
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            json_decode('{}');
            $decoded = json_decode($candidate, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function repairJson(string $json): string
    {
        $json = trim($json);
        $json = str_replace(
            ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}", '«', '»'],
            ['"', '"', "'", "'", '"', '"'],
            $json
        );
        $json = preg_replace('/,\s*([}\]])/m', '$1', $json) ?? $json;

        return $json;
    }

    protected function attemptCloseTruncatedJson(string $json): ?string
    {
        $json = trim($json);
        if (! str_starts_with($json, '{')) {
            return null;
        }

        $openBraces = substr_count($json, '{') - substr_count($json, '}');
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');

        if ($openBraces <= 0 && $openBrackets <= 0) {
            return null;
        }

        if (substr_count($json, '"') % 2 !== 0) {
            $json .= '"';
        }

        $json .= str_repeat(']', max(0, $openBrackets));
        $json .= str_repeat('}', max(0, $openBraces));

        return $json;
    }

    protected function extractBalancedJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($content);

        for ($i = $start; $i < $length; $i++) {
            $char = $content[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '{') {
                $depth++;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    protected function extractJsonFromMarkdown(string $content): ?string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    protected function extractJsonObject(string $content): ?string
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($content, $start, $end - $start + 1);
    }

    protected function resolveModelName(?string $model): string
    {
        $model = $model ?? config('gemini.default_model', 'gemini-2.5-flash-lite');

        if (str_starts_with($model, 'google/')) {
            return substr($model, strlen('google/'));
        }

        return $model;
    }
}
