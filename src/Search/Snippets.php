<?php

namespace Daun\StatamicLoupe\Search;

class Snippets {
    protected string $pattern;

    public function __construct(
        protected string $startTag = '<mark>',
        protected string $endTag = '</mark>',
        protected int $surroundingWords = 4,
        protected string $separator = '...'
    ) {
        $this->pattern = sprintf('/%s.*?%s/s', preg_quote($startTag, '/'), preg_quote($endTag, '/'));
        $this->separator = sprintf(' %s ', trim($this->separator));
    }

    public function generate(string $text): string {
        if (mb_strlen($text) === 0 || mb_strpos($text, $this->startTag) === false) {
            return '';
        }

        $marks = $this->getAllMarks($text);
        if (empty($marks)) {
            return '';
        }

        $words = $this->getAllWords($text);

        $boundaries = [];
        foreach ($marks as [$mark, $pos]) {
            $boundaries[] = $this->getSurroundingWords($words, $pos, strlen($mark));
        }

        $boundaries = $this->mergeBoundaries($words, $boundaries);
        $snippets = array_map(fn($b) => $this->buildSnippet($text, $b), $boundaries);

        return implode($this->separator, $snippets);
    }

    private function getAllMarks(string $text): array {
        preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0] ?? [];
    }

    private function getAllWords(string $text): array {
        preg_match_all('/\S+/s', $text, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0] ?? [];
    }

    private function getSurroundingWords(array $words, int $matchPos, int $matchLength): array {
        $startPos = $matchPos;
        $endPos = $matchPos + $matchLength;

        // Find the word that contains our match start
        $matchStartIdx = -1;
        $matchEndIdx = -1;

        for ($i = 0; $i < count($words); $i++) {
            $wordPos = $words[$i][1];
            $wordEnd = $wordPos + strlen($words[$i][0]);

            if ($wordPos <= $startPos && $wordEnd >= $startPos && $matchStartIdx === -1) {
                $matchStartIdx = $i;
            }
            if ($wordPos <= $endPos && $wordEnd >= $endPos) {
                $matchEndIdx = $i;
                break;
            }
        }

        if ($matchStartIdx === -1 || $matchEndIdx === -1) {
            return [];
        }

        return [
            'match_start' => $matchStartIdx,
            'match_end' => $matchEndIdx,
            'text_start' => $words[$matchStartIdx][1],
            'text_end' => $words[$matchEndIdx][1] + strlen($words[$matchEndIdx][0])
        ];
    }

    private function mergeBoundaries(array $words, array $boundaries): array {
        if (empty($boundaries)) {
            return [];
        }

        $result = [];
        $current = array_shift($boundaries);

        foreach ($boundaries as $next) {
            // Calculate word distance between matches
            $wordsBetween = $next['match_start'] - $current['match_end'];

            if ($wordsBetween <= $this->surroundingWords * 2) {
                // Matches are close - merge them with minimal context between
                $current['match_end'] = $next['match_end'];
                $current['text_end'] = $next['text_end'];
            } else {
                // Matches are far - add proper context on both sides
                $leftWords = array_slice($words,
                    max(0, $current['match_end']),
                    $this->surroundingWords
                );
                $rightWords = array_slice($words,
                    max(0, $next['match_start'] - $this->surroundingWords),
                    $this->surroundingWords
                );

                // Add first match with its right context
                $result[] = [
                    $current['text_start'],
                    $leftWords[count($leftWords) - 1][1] + strlen($leftWords[count($leftWords) - 1][0])
                ];

                // Start new current with left context of next match
                $current = [
                    'text_start' => $rightWords[0][1],
                    'text_end' => $next['text_end'],
                    'match_start' => $next['match_start'],
                    'match_end' => $next['match_end']
                ];
            }
        }

        // Add the last segment
        $result[] = [
            $current['text_start'],
            $current['text_end']
        ];

        return $result;
    }

    private function buildSnippet(string $text, array $boundaries): string {
        // Extract the actual text portion from original text
        return substr($text, $boundaries[0], $boundaries[1] - $boundaries[0]);
    }
}
