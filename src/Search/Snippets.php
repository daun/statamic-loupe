<?php

namespace Daun\StatamicLoupe\Search;

class Snippets {
    protected string $pattern;

    public function __construct(
        protected string $startTag = '<mark>',
        protected string $endTag = '</mark>',
        protected int $surroundingWords = 4,
        protected string $separator = ' [...] '
    ) {
        $this->pattern = sprintf('/%s.*?%s/s', preg_quote($startTag, '/'), preg_quote($endTag, '/'));
    }

    public function generate(string $text): string {
        if (mb_strlen($text) === 0 || mb_strpos($text, $this->startTag) === false) {
            return '';
        }

        // Find all matches with their positions
        preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return '';
        }

        // Convert text to array of words with their positions
        $words = $this->getWordsWithPositions($text);

        $snippets = [];
        foreach ($matches[0] as $match) {
            $matchText = $match[0];
            $matchPos = $match[1];

            // Find surrounding word indices
            $surroundingWords = $this->getSurroundingWords(
                $words,
                $matchPos,
                strlen($matchText)
            );

            if ($surroundingWords) {
                $snippets[] = $this->buildSnippet($words, $surroundingWords);
            }
        }

        // ray($text, $words, $snippets);

        // Merge overlapping snippets
        return $this->mergeSnippets($snippets);
    }

    private function getSurroundingWords(array $words, int $matchPos, int $matchLength): array {
        $startPos = $matchPos;
        $endPos = $matchPos + $matchLength;

        $startIdx = $endIdx = -1;

        // Find the words that contain our match
        for ($i = 0; $i < count($words); $i++) {
            $wordPos = $words[$i][1];
            if ($wordPos <= $startPos && $startIdx === -1) {
                $startIdx = $i;
            }
            if ($wordPos + strlen($words[$i][0]) >= $endPos) {
                $endIdx = $i;
                break;
            }
        }

        if ($startIdx === -1 || $endIdx === -1) {
            return [];
        }

        // Calculate surrounding word boundaries
        $start = max(0, $startIdx - $this->surroundingWords);
        $end = min(count($words) - 1, $endIdx + $this->surroundingWords);

        return [$start, $end];
    }

    private function getWordsWithPositions(string $text): array {
        preg_match_all('/\S+/s', $text, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0];
    }

    // private function getWordsWithPositions(string $text): array {
    //     // We should preserve the HTML structure while identifying word boundaries
    //     // Split by whitespace but preserve HTML tags
    //     preg_match_all('/(?:<[^>]+>)|(?:\S+)/s', $text, $matches, PREG_OFFSET_CAPTURE);
    //     return $matches[0];
    // }

    // private function buildSnippet(array $words, array $boundaries): string {
    //     $snippet = [];
    //     for ($i = $boundaries[0]; $i <= $boundaries[1]; $i++) {
    //         // Add the word or HTML tag as is
    //         $snippet[] = $words[$i][0];
    //     }
    //     // Join with space, but don't add space before or after HTML tags
    //     return preg_replace('/\s*(<\/?[^>]+>)\s*/', '$1', implode(' ', $snippet));
    // }

    private function buildSnippet(array $words, array $boundaries): string {
        $snippet = [];
        for ($i = $boundaries[0]; $i <= $boundaries[1]; $i++) {
            $snippet[] = $words[$i][0];
        }
        return implode(' ', $snippet);
    }

    private function mergeSnippets(array $snippets): string {
        if (empty($snippets)) {
            return '';
        }

        $result = [$snippets[0]];

        for ($i = 1; $i < count($snippets); $i++) {
            $current = $snippets[$i];
            $last = end($result);

            // Check if snippets overlap
            if (strpos($current, $last) !== false ||
                strpos($last, $current) !== false) {
                // Use the longer snippet
                if (strlen($current) > strlen($last)) {
                    array_pop($result);
                    $result[] = $current;
                }
            } else {
                $result[] = $current;
            }
        }

        return implode($this->separator, $result);
    }
}
