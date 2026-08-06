# Statamic Loupe Search Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daun/statamic-loupe.svg)](https://packagist.org/packages/daun/statamic-loupe) [![Test Status](https://img.shields.io/github/actions/workflow/status/daun/statamic-loupe/ci.yml?label=tests)](https://github.com/daun/statamic-loupe/actions/workflows/ci.yml) [![License](https://img.shields.io/github/license/daun/statamic-loupe.svg)](https://github.com/daun/statamic-loupe/blob/master/LICENSE) <!-- [![Code Coverage](https://img.shields.io/codecov/c/github/daun/statamic-loupe)](https://app.codecov.io/gh/daun/statamic-loupe) -->

**This addon provides a [Loupe](https://github.com/loupe-php/loupe) search driver for Statamic sites.**

## Loupe

...is a local SQLite search engine that is easy to set up and requires no additional infrastructure.

- Only requires PHP and SQLite, nothing else
- Tolerates typos, supports stemming and compound words
- Supports `-negated` queries and `"phrase search"`
- Supports filtering and ordering on geo distance
- Highlights and crops relevant snippets
- Sorts by relevance

## Requirements

- SQLite PDO 3.35 or higher

## Installation

```sh
composer require daun/statamic-loupe
```

Add the new driver to `statamic/search.php`.

```diff
'drivers' => [
    'local' => ['path' => storage_path('statamic/search')],
+   'loupe' => [],
],
```

Now set your indexes to use the new driver.

```diff
'indexes' => [
    'default' => [
-       'driver' => 'local',
+       'driver' => 'loupe',
        'searchables' => 'content',
    ],
],
```

## Configuration

While Loupe will work just fine with the default settings, there are a few knobs
you can turn to fine-tune the indexing and ranking of results. Most of these map
directly to [Loupe's configuration items](https://github.com/loupe-php/loupe/blob/main/docs/configuration.md).

The values below are the default values. It is recommended to only define the config
keys you specifically care about. That way, the defaults keep applying when a future
release of Loupe improves them.

```php
'drivers' => [
    'loupe' => [
        // Storage directory of Loupe's index database
        'path' => storage_path('statamic/loupe'),

        // Relevance factors and their order of importance
        'ranking_rules' => ['words', 'typo', 'proximity', 'attribute', 'exactness'],

        // Minimum ranking score of results to return (between `0.0` and `1.0`)
        'ranking_score_threshold' => 0,

        // Languages of the indexed content, see "Languages" section below
        'languages' => 'auto',

        // Words ignored when indexing and searching
        'stop_words' => [],

        // Whether typo tolerance is enabled
        'typo_tolerance_enabled' => true,

        // Whether typo tolerance is enabled in prefix search
        'typo_tolerance_for_prefix_search' => false,

        // Minimum word length to allow searching by prefix
        'min_token_length_for_prefix_search' => 2,

        // Strategy for multi-word queries, see "Matching strategy" section below
        'matching_strategy' => 'any',

        // Maximum number of results returned per search
        'hits_per_page' => null,

        // Number of documents Loupe considers per matching term before ranking
        // Lowering this speeds up searches, but may drop relevant results
        'max_total_hits' => 1000,

        // Maximum number of words allowed in a search
        // Higher values allow more complex queries but may impact performance
        'max_query_tokens' => 10,
    ],
],
```

## Languages

Loupe uses the language of your content for stemming, normalizing and decomposing compound words.
Decomposition is currently supported for German and English.

By default, the languages are derived from your sites, so most setups need no configuration:

| Setup | Derived languages |
| --- | --- |
| Single site | The site's language, e.g. `['de']` |
| Multiple sites, one index per site | That site's language, e.g. `['de']` |
| Multiple sites, one shared index | All site languages, e.g. `['de', 'en']` |

Given a single language, Loupe skips language detection entirely, which is considerably faster.
Given several, it narrows detection to those candidates. You can also configure the languages
explicitly:

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'languages' => ['de'],
    ],
],
```

If your content is in a different language than your site, or a single site mixes languages, set
an empty array to let Loupe detect the language of every document by itself:

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'languages' => [],
    ],
],
```

## Matching strategy

The matching strategy decides whether a document has to contain every word of a query (`all`) or
whether any of them is enough (`any`). Loupe defaults to `any` and lets the ranking do the work:
a query like `winter tyre pressure` puts documents containing all three words on top, followed by
documents about winter tyres, followed by documents merely mentioning pressure. Nothing is
discarded, so `ranking_score_threshold` is the knob to cut off a long tail of weak matches.

With `all`, every additional word narrows the result set instead, and a query matching no document
returns nothing, no matter how close it was.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'matching_strategy' => 'all',
    ],
],
```

Which one fits depends on what your users do when they type a second word.

- Use `any` when they are exploring and don't know the wording of your content, as in site search or
documentation: every extra word is a hint about relevance, not a requirement.
- Use `all` when they know what they are after and are narrowing down a list, as in product catalogues
or ticket systems: an extra word means "and also this", and results ignoring it feel like noise.

## Stop words

Ignoring very common words of your content languages reduces the index size and keeps them from
influencing relevance. Define them in the `stop_words` option.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'stop_words' => ['the', 'a', 'an', 'of', 'and', 'or'],
    ],
],
```

## Search highlights

Enable term highlighting to wrap occurrences of search words in `<mark>` tags. You'll need to
explicitly define the attributes to apply highlighting in.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
+       'highlight_attributes' => ['title', 'summary'],
    ],
],
```

You can now display the configured fields from the `search_highlights` namespace:

```antlers
{{ search:results }}
  <h2>{{ search_highlights:title }}</h2>
  <p>{{ search_highlights:summary }}</p>
{{ /search:results }}
```

You can configure the tags to use for highlighting terms. The default is plain `<mark>`.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
        'highlight_attributes' => ['title', 'summary'],
+       'highlight_tags' => ['<span class="highlight">', '</span>'],
    ],
],
```

## Search snippets

Snippets are condensed highlights collecting only the actual matches and the text immediately
surrounding them. This allows quick skimming of search results for relevancy and context.

> <mark>Lorem ipsum</mark> dolor sit amet, consetetur ... no sea takimata sanctus est <mark>lorem</mark> est <mark>ipsum</mark> dolor sit amet. <mark>Lorem ipsum</mark> dolor sit amet, consetetur ... dolore te feugait nulla facilisi <mark>lorem ipsum</mark> dolor sit amet, consectetuer ...

To enable snippets, define the attributes you want to generate them for, as well
as the maximum length in characters of each snippet fragment. Matches inside snippets are
highlighted automatically, so there is no need to also list the attribute in
`highlight_attributes`.

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
        'highlight_attributes' => ['title'],
+       'snippet_attributes' => ['summary' => 150],
    ],
],
```

Then use the `search_snippets` namespace to display the formatted fields:

```antlers
{{ search:results }}
  <h2>{{ search_snippets:title }}</h2>
  <p>{{ search_snippets:summary }}</p>
{{ /search:results }}
```

A snippet is assembled from up to `snippet_max_fragments` fragments, joined and delimited
by `snippet_marker`:

```diff
'indexes' => [
    'default' => [
        'driver' => 'loupe',
        'searchables' => 'content',
        'snippet_attributes' => ['summary' => 150],
+       'snippet_max_fragments' => 5,
+       'snippet_marker' => '…',
    ],
],
```

## License

[MIT](https://opensource.org/licenses/MIT)
