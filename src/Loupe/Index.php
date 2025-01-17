<?php

namespace Daun\StatamicLoupe\Loupe;

use Daun\StatamicLoupe\Search\Snippets;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\SearchParameters;
use Statamic\Contracts\Search\Searchable;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Statamic\Search\Result;

class Index extends BaseIndex
{
    protected ?Loupe $client = null;

    protected ?Configuration $configuration = null;

    protected array $defaults = [
        'fields' => ['title'],
        'chunk' => 100,
        'max_query_tokens' => 10,
        'min_token_length_for_prefix_search' => 2,
        'stemming_languages' => [],
        'typo_tolerance_enabled' => true,
        'typo_tolerance_alphabet_size' => 4,
        'typo_tolerance_index_length' => 14,
        'typo_tolerance_for_prefix_search' => false,
        'ranking_score_threshold' => 0,
        'highlight_attributes' => [],
        'highlight_tags' => ['<mark>', '</mark>'],
        'snippet_attributes' => [],
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
            ->withHitsPerPage(999)
            ->withShowRankingScore(true)
            ->withRankingScoreThreshold($this->config['ranking_score_threshold'])
            ->withAttributesToHighlight(
                array_unique([...$this->config['highlight_attributes'], ...$this->config['snippet_attributes']]),
                $this->config['highlight_tags'][0],
                $this->config['highlight_tags'][1]
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
        return $this->configuration ??= Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(
                collect($this->config['fields'])->keyBy(fn ($f) => $f)->except(['id'])->values()->all()
            )
            ->withMaxQueryTokens($this->config['max_query_tokens'])
            ->withMinTokenLengthForPrefixSearch($this->config['min_token_length_for_prefix_search'])
            ->withLanguages($this->config['stemming_languages'])
            ->withTypoTolerance(
                $this->config['typo_tolerance_enabled']
                    ? TypoTolerance::create()
                        ->withAlphabetSize($this->config['typo_tolerance_alphabet_size'])
                        ->withIndexLength($this->config['typo_tolerance_index_length'])
                    : TypoTolerance::disabled()
            );
    }

    public function delete($document)
    {
        $this->client()->deleteDocument($document->getSearchReference());
    }

    public function exists()
    {
        return $this->filesystem->exists($this->path());
    }

    protected function insertDocuments(Documents $documents)
    {
        // After upgrading Loupe, a reindex might be required
        if ($this->client()->needsReindex()) {
            $this->truncateIndex();
        }

        $this->client()->addDocuments($documents->all());
    }

    public function insertMultiple($documents)
    {
        (new Documents($documents))
            ->chunk($this->config['chunk'] ?? 100)
            ->each(function ($documents) {
                $documents = (new Documents($documents))
                    ->map(fn (Searchable $item) => [
                        ...$this->searchables()->fields($item),
                        'id' => $item->getSearchReference(),
                    ])
                    ->values();

                $this->insertDocuments($documents);
            });

        return $this;
    }

    public function update()
    {
        $this->truncateIndex();

        $this->searchables()->lazy()->each(fn ($searchables) => $this->insertMultiple($searchables));

        return $this;
    }

    protected function deleteIndex()
    {
        $this->filesystem->cleanDirectory($this->path());
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
        $attributes = $this->config['snippet_attributes'] ?? [];
        if (empty($attributes)) {
            return [];
        }

        $this->snippetAttributes ??= collect($attributes)
            ->filter(fn ($value, $key) => is_string($key) || is_string($value))
            ->mapWithKeys(fn ($value, $key) => is_string($key) ? [$key => $value] : [$value => 10])
            ->all();

        [$start, $end] = $this->config['highlight_tags'];

        return collect($this->snippetAttributes)
            ->map(function ($words, $attr) use ($fields, $start, $end) {
                try {
                    return (new Snippets($start, $end, $words))->generate($fields[$attr]);
                } catch (\Exception $e) {
                    return Str::limit($fields[$attr], limit: 200, preserveWords: true);
                }
            })
            ->all();
    }
}
