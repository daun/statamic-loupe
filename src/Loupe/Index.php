<?php

namespace Daun\StatamicLoupe\Loupe;

use Daun\StatamicLoupe\Search\Snippets;
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
    protected Loupe $client;

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
        protected Manager $manager,
        string $name,
        array $config,
        ?string $locale = null
    ) {
        $config = [...$this->defaults, ...$config];
        parent::__construct($name, $config, $locale);

        $this->client = $this->manager->get($this->name, $this->configuration());
    }

    public function client(): Loupe
    {
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

        $result = $this->client->search($parameters);

        return collect($result->getHits())
            ->map(fn ($hit) => [
                ...$hit,
                'reference' => $hit['id'],
                'search_score' => floor($hit['_rankingScore'] * 100),
            ]);
    }

    protected function configuration(): Configuration
    {
        return Configuration::create()
            ->withPrimaryKey('id')
            ->withSearchableAttributes(Arr::except($this->config['fields'], ['id']))
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
        $this->client->deleteDocument($document->getSearchReference());
    }

    public function exists()
    {
        return $this->manager->indexExists($this->name);
    }

    protected function insertDocuments(Documents $documents)
    {
        // After upgrading Loupe, a reindex might be required
        if ($this->client->needsReindex()) {
            $this->resetIndex();
        }

        $this->client->addDocuments($documents->all());
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
        $this->resetIndex();

        $this->searchables()->lazy()->each(fn ($searchables) => $this->insertMultiple($searchables));

        return $this;
    }

    protected function deleteIndex()
    {
        $this->manager->dropIndex($this->name);
    }

    protected function createIndex()
    {
        $this->manager->createIndex($this->name);
    }

    protected function resetIndex()
    {
        $this->manager->clearIndex($this->name);
    }

    public function extraAugmentedResultData(Result $result)
    {
        $raw = $result->getRawResult();

        return [
            'search_score' => $raw['_rankingScore'] ?? null,
            'search_highlights' => Arr::only($raw['_formatted'] ?? [], $this->config['highlight_attributes']),
            'search_snippets' => $this->createSnippets($raw['_formatted'] ?? [], $this->config['snippet_attributes']),
        ];
    }

    protected function createSnippets(array $highlights, array $attributes): array
    {
        if (empty($attributes)) {
            return [];
        }

        $this->snippetAttributes ??= collect($attributes)
            ->filter(fn ($value, $key) => is_string($key) || is_string($value))
            ->mapWithKeys(fn ($value, $key) => is_string($key) ? [$key => $value] : [$value => 10])
            ->all();

        [$start, $end] = $this->config['highlight_tags'];

        return collect($this->snippetAttributes)
            ->map(function ($words, $attr) use ($highlights, $start, $end) {
                try {
                    return (new Snippets($start, $end, $words))->generate($highlights[$attr]);
                } catch (\Exception $e) {
                    return Str::limit($highlights[$attr], limit: 200, preserveWords: true);
                }
            })
            ->all();
    }
}
