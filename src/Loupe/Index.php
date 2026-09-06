<?php

namespace Daun\StatamicLoupe\Loupe;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Loupe\Loupe\BrowseParameters;
use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\SearchParameters;
use Statamic\Facades\Site;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Statamic\Search\Result;

class Index extends BaseIndex
{
    protected ?Loupe $client = null;

    protected ?Configuration $configuration = null;

    /**
     * Empty values defer to Loupe's own defaults instead of freezing them here.
     */
    protected array $defaults = [
        'fields' => ['title'],
        'languages' => 'auto',
        'matching_strategy' => null,
        'max_query_tokens' => null,
        'max_total_hits' => null,
        'min_token_length_for_prefix_search' => 2,
        'ranking_rules' => [],
        'stop_words' => [],
        'typo_tolerance_enabled' => true,
        'typo_tolerance_alphabet_size' => null,
        'typo_tolerance_index_length' => null,
        'typo_tolerance_for_prefix_search' => null,
        'ranking_score_threshold' => 0,
        'hits_per_page' => null,
        'highlight_attributes' => [],
        'highlight_tags' => ['<mark>', '</mark>'],
        'snippet_attributes' => [],
        'snippet_length' => 50,
        'snippet_marker' => '…',
        'snippet_max_fragments' => 5,
    ];

    protected ?array $snippetAttributes = null;

    public function __construct(
        protected Factory $factory,
        protected Filesystem $filesystem,
        string $name,
        array $config = [],
        ?string $locale = null
    ) {
        $config = [...$this->defaults, ...$config];
        parent::__construct($name, $config, $locale);
    }

    public function base(): string
    {
        return Str::finish($this->config['path'] ?? storage_path('statamic/loupe'), '/');
    }

    public function dir(): string
    {
        return $this->base().$this->name;
    }

    public function path(): string
    {
        return $this->base().$this->name.'/loupe.db';
    }

    public function client(): Loupe
    {
        if (! $this->client) {
            $this->createIndex();
            $this->client = $this->factory->create($this->dir(), $this->configuration());
        }

        return $this->client;
    }

    public function search($query)
    {
        return (new Query($this))->query($query);
    }

    public function lookup($query)
    {
        $parameters = SearchParameters::create()
            ->withQuery($query)
            ->withMatchingStrategy($this->matchingStrategy())
            ->withHitsPerPage($this->hitsPerPage())
            ->withShowRankingScore(true)
            ->withRankingScoreThreshold($this->config['ranking_score_threshold'])
            ->withAttributesToHighlight(
                array_unique([...$this->config['highlight_attributes'], ...array_keys($this->snippetAttributes())]),
                $this->config['highlight_tags'][0],
                $this->config['highlight_tags'][1]
            )
            ->withAttributesToCrop(
                $this->snippetAttributes(),
                $this->config['snippet_length'],
                $this->config['snippet_marker'],
                $this->config['snippet_max_fragments']
            );

        $result = $this->client()->search($parameters);

        return collect($result->getHits())
            ->map(fn ($hit) => [
                ...$hit,
                'reference' => $hit['id'],
                'search_score' => floor($hit['_rankingScore'] * 100),
            ]);
    }

    public function configuration(): Configuration
    {
        if ($this->configuration) {
            return $this->configuration;
        }

        $configuration = Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(
                collect($this->config['fields'])->keyBy(fn ($f) => $f)->except(['id'])->values()->all()
            )
            ->withLanguages($this->languages())
            ->withTypoTolerance($this->typoTolerance());

        $options = [
            'max_query_tokens' => 'withMaxQueryTokens',
            'max_total_hits' => 'withMaxTotalHits',
            'min_token_length_for_prefix_search' => 'withMinTokenLengthForPrefixSearch',
            'ranking_rules' => 'withRankingRules',
            'stop_words' => 'withStopWords',
        ];

        foreach ($options as $key => $method) {
            if (filled($this->config[$key] ?? null)) {
                $configuration = $configuration->{$method}($this->config[$key]);
            }
        }

        return $this->configuration = $configuration;
    }

    protected function typoTolerance(): TypoTolerance
    {
        if (! ($this->config['typo_tolerance_enabled'] ?? true)) {
            return TypoTolerance::disabled();
        }

        $tolerance = TypoTolerance::create();

        $options = [
            'typo_tolerance_alphabet_size' => 'withAlphabetSize',
            'typo_tolerance_index_length' => 'withIndexLength',
            'typo_tolerance_for_prefix_search' => 'withEnabledForPrefixSearch',
        ];

        foreach ($options as $key => $method) {
            if (! is_null($this->config[$key] ?? null)) {
                $tolerance = $tolerance->{$method}($this->config[$key]);
            }
        }

        return $tolerance;
    }

    public function matchingStrategy(): string
    {
        return $this->config['matching_strategy'] ?: SearchParameters::create()->getMatchingStrategy();
    }

    public function hitsPerPage(): int
    {
        $max = min($this->config['max_total_hits'] ?? SearchParameters::MAX_LIMIT, SearchParameters::MAX_LIMIT);

        return min($this->config['hits_per_page'] ?? $max, $max);
    }

    public function languages(): array
    {
        $languages = $this->config['languages'] ?? 'auto';

        if (is_array($languages)) {
            return array_values(array_unique($languages));
        }

        if ($languages !== 'auto') {
            return [];
        }

        $sites = $this->locale()
            ? collect([Site::get($this->locale())])->filter()
            : Site::all();

        return $sites->map->lang()->filter()->unique()->values()->all();
    }

    public function delete($document)
    {
        $this->client()->deleteDocument($document->getSearchReference());
    }

    public function exists()
    {
        return $this->filesystem->exists($this->path());
    }

    public function insertDocuments(Documents $documents)
    {
        // After upgrading Loupe, a reindex might be required
        if ($this->client()->needsReindex()) {
            $this->truncateIndex();
        }

        $documentsWithIds = $documents
            ->map(fn (array $doc, string $reference) => [...$doc, 'id' => $reference])
            ->values();

        $this->client()->addDocuments($documentsWithIds->all());
    }

    public function update()
    {
        $seen = [];

        $this->searchables()->lazy()->each(function ($searchables) use (&$seen) {
            $references = $searchables->map(function ($reference) use (&$seen) {
                $seen[$reference] = true;

                return $reference;
            });

            $this->insertMultiple($references);
        });

        $this->prune($seen);

        return $this;
    }

    /**
     * @param  array<string, true>  $seen
     */
    protected function prune(array $seen): void
    {
        $stale = [];
        $page = 1;

        do {
            $result = $this->client()->browse(
                BrowseParameters::create()
                    ->withAttributesToRetrieve(['id'])
                    ->withHitsPerPage(BrowseParameters::MAX_LIMIT)
                    ->withPage($page)
            );

            foreach ($result->getHits() as $hit) {
                if (! isset($seen[$hit['id']])) {
                    $stale[] = $hit['id'];
                }
            }

            $page++;
        } while ($page <= $result->getTotalPages());

        if ($stale !== []) {
            $this->client()->deleteDocuments($stale);
        }
    }

    protected function deleteIndex()
    {
        $this->client = null;
        $this->filesystem->cleanDirectory($this->dir());
    }

    protected function createIndex()
    {
        $dir = $this->dir();
        $db = $this->path();

        if (! $this->filesystem->exists($db)) {
            $this->filesystem->ensureDirectoryExists($dir);
            $this->filesystem->put($db, '');
        }

        if (! $this->filesystem->isFile($db)) {
            throw new Exception(sprintf('The Loupe index "%s" does not exist and cannot be created.', $db));
        }

        if (! $this->filesystem->isWritable($db)) {
            throw new Exception(sprintf('The Loupe index "%s" is not writable.', $db));
        }
    }

    protected function truncateIndex()
    {
        $this->client()->deleteAllDocuments();
    }

    public function extraAugmentedResultData(Result $result)
    {
        $raw = $result->getRawResult();

        return [
            'search_score' => $raw['_rankingScore'] ?? null,
            'search_highlights' => $this->getHighlights($raw['_formatted'] ?? []),
            'search_snippets' => $this->getSnippets($raw['_formatted'] ?? []),
        ];
    }

    protected function getHighlights(array $fields): array
    {
        return Arr::only($fields, $this->config['highlight_attributes'] ?? []);
    }

    protected function getSnippets(array $fields): array
    {
        return Arr::only($fields, array_keys($this->snippetAttributes()));
    }

    /**
     * Snippet attributes keyed by name, with their crop length in characters.
     * Mirrors the shapes Loupe accepts: ['title'] and ['title' => 60].
     *
     * @return array<string, int>
     */
    protected function snippetAttributes(): array
    {
        return $this->snippetAttributes ??= collect($this->config['snippet_attributes'] ?? [])
            ->filter(fn ($value, $key) => is_string($key) || is_string($value))
            ->mapWithKeys(fn ($value, $key) => is_string($key)
                ? [$key => (int) $value]
                : [$value => (int) $this->config['snippet_length']])
            ->all();
    }
}
