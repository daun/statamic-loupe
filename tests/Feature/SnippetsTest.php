use Daun\StatamicLoupe\Search\Snippets;

<?php

use Daun\StatamicLoupe\Search\Snippets;

it('returns empty string for empty input', function () {
    $snippets = new Snippets();
    expect($snippets->generate(''))->toEqual('');
});

it('returns empty string when no start tag is found', function () {
    $snippets = new Snippets();
    expect($snippets->generate('This is a test string without start tag.'))->toEqual('');
});

it('returns snippet with single match', function () {
    $snippets = new Snippets();
    $text = 'This is a <mark>test</mark> string.';
    $expected = 'This is a <mark>test</mark> string.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('returns snippet with multiple matches', function () {
    $snippets = new Snippets();
    $text = 'This is a <mark>test</mark> string with another <mark>match</mark>.';
    $expected = 'This is a <mark>test</mark> string with another <mark>match</mark>.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('returns snippet with surrounding words', function () {
    $snippets = new Snippets();
    $text = 'This is a <mark>test</mark> string with some surrounding words.';
    $expected = 'This is a <mark>test</mark> string with some surrounding words.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('returns snippet with separator for distant matches', function () {
    $snippets = new Snippets();
    $text = 'This is a <mark>test</mark> string. And here is another <mark>match</mark> far away.';
    $expected = 'This is a <mark>test</mark> string. ... And here is another <mark>match</mark> far away.';
    expect($snippets->generate($text))->toEqual($expected);
});

it('handles custom tags and separator', function () {
    $snippets = new Snippets('[start]', '[end]', 4, '---');
    $text = 'This is a [start]test[end] string with a custom separator.';
    $expected = 'This is a [start]test[end] string with a custom separator.';
    expect($snippets->generate($text))->toEqual($expected);
});
