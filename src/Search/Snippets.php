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

        $marks = $this->getAllMarks($text);
        if (empty($marks)) {
            return '';
        }

        $words = $this->getAllWords($text);

        // return $text;

        $boundaries = [];
        foreach ($marks as [$mark, $pos]) {
            ray($mark, $pos, $this->getSurroundingWords($words, $pos, strlen($mark)));
            $boundaries[] = $this->getSurroundingWords($words, $pos, strlen($mark));
        }

        $boundaries = $this->mergeBoundaries($boundaries);
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

        // Find the word positions that will form our snippet boundaries
        $snippetStart = $startPos;
        $snippetEnd = $endPos;

        // Count backwards to find start boundary
        $wordsBack = 0;
        for ($i = count($words) - 1; $i >= 0; $i--) {
            if ($words[$i][1] < $startPos) {
                if ($wordsBack >= $this->surroundingWords) {
                    $snippetStart = $words[$i][1];
                    break;
                }
                $wordsBack++;
            }
        }

        // Count forwards to find end boundary
        $wordsForward = 0;
        for ($i = 0; $i < count($words); $i++) {
            if ($words[$i][1] > $endPos) {
                if ($wordsForward >= $this->surroundingWords) {
                    $snippetEnd = $words[$i][1] + strlen($words[$i][0]);
                    break;
                }
                $wordsForward++;
            }
        }

        return [$snippetStart, $snippetEnd];
    }

    private function buildSnippet(string $text, array $boundaries): string {
        // Extract the actual text portion from original text
        return substr($text, $boundaries[0], $boundaries[1] - $boundaries[0]);
    }

    private function mergeBoundaries(array $boundaries): array {
        $boundaries = array_filter($boundaries);

        if (empty($boundaries)) {
            return [];
        }

        $result = [];

        $last = array_shift($boundaries);
        foreach ($boundaries as $boundary) {
            if ($boundary[0] <= $last[1]) {
                $last[1] = $boundary[1];
            } else {
                $result[] = $last;
                $last = $boundary;
            }
        }

        return $result;
    }
}
