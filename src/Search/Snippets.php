<?php

namespace Daun\StatamicLoupe\Search;

class Snippets
{
    protected string $pattern;

    public function __construct(
        protected string $startTag = '<mark>',
        protected string $endTag = '</mark>',
        protected int $surroundingWords = 5,
        protected string $separator = '...'
    ) {
        $this->pattern = sprintf('/%s.*?%s/s', preg_quote($startTag, '/'), preg_quote($endTag, '/'));
        $this->separator = sprintf(' %s ', trim($this->separator));
    }

    public function generate(string $text): string
    {
        $len = mb_strlen($text);

        if ($len === 0 || mb_strpos($text, $this->startTag) === false) {
            return '';
        }

        $marks = $this->getAllMarks($text);
        if (empty($marks)) {
            return '';
        }

        $words = $this->getAllWords($text);
        $boundaries = $this->getBoundariesWithContext($words, $marks);

        $snippets = array_map(fn ($b) => $this->buildSnippet($text, $b), $boundaries);

        if (!empty($boundaries) && $boundaries[0][0] > 0) {
            $snippets[0] = ltrim($this->separator) . $snippets[0];
        }
        if (!empty($boundaries) && end($boundaries)[1] < $len) {
            $snippets[count($snippets) - 1] .= rtrim($this->separator);
        }

        return implode($this->separator, $snippets);
    }

    private function getAllMarks(string $text): array
    {
        preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0] ?? [];
    }

    private function getAllWords(string $text): array
    {
        preg_match_all('/\S+/s', $text, $matches, PREG_OFFSET_CAPTURE);
        return $matches[0];
    }

    private function getBoundariesWithContext(array $words, array $marks): array
    {
        if (empty($marks)) {
            return [];
        }

        $result = [];
        $currentSegment = null;

        foreach ($marks as [$mark, $matchPos]) {
            $matchLength = mb_strlen($mark);
            $matchEnd = $matchPos + $matchLength;

            // Find word boundaries for current match
            $matchStartIdx = -1;
            $matchEndIdx = -1;

            for ($i = 0; $i < count($words); $i++) {
                $wordPos = $words[$i][1];
                $wordEnd = $wordPos + mb_strlen($words[$i][0]);

                if ($wordPos <= $matchPos && $wordEnd >= $matchPos && $matchStartIdx === -1) {
                    $matchStartIdx = $i;
                }
                if ($wordPos <= $matchEnd && $wordEnd >= $matchEnd) {
                    $matchEndIdx = $i;
                    break;
                }
            }

            if ($matchStartIdx === -1 || $matchEndIdx === -1) {
                continue;
            }

            // Calculate boundaries with context
            $contextStart = max(0, $matchStartIdx - $this->surroundingWords);
            $contextEnd = min(count($words) - 1, $matchEndIdx + $this->surroundingWords);

            $segmentStart = $words[$contextStart][1];
            $segmentEnd = $words[$contextEnd][1] + mb_strlen($words[$contextEnd][0]);

            // Handle segment merging
            if ($currentSegment !== null) {
                $wordsBetween = $matchStartIdx - $currentSegment['lastWordIdx'];

                if ($wordsBetween <= $this->surroundingWords * 2) {
                    // Merge with previous segment
                    $currentSegment['end'] = $segmentEnd;
                    $currentSegment['lastWordIdx'] = $matchEndIdx;
                    continue;
                }

                // Store previous segment and start new one
                $result[] = [$currentSegment['start'], $currentSegment['end']];
            }

            // Start new segment
            $currentSegment = [
                'start' => $segmentStart,
                'end' => $segmentEnd,
                'lastWordIdx' => $matchEndIdx
            ];
        }

        // Add final segment
        if ($currentSegment !== null) {
            $result[] = [$currentSegment['start'], $currentSegment['end']];
        }

        return $result;
    }

    private function buildSnippet(string $text, array $boundaries): string
    {
        return substr($text, $boundaries[0], $boundaries[1] - $boundaries[0]);
    }
}
