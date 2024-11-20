<?php

namespace Daun\StatamicLoupe\Loupe;

use Illuminate\Support\Arr;
use Loupe\Loupe\Config\TypoTolerance;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\SearchParameters;
use Statamic\Contracts\Search\Searchable;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;

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
    ];

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

    public function search($query)
    {
        return (new Query($this))->query($query);
    }

    public function lookup($query)
    {
        $parameters = SearchParameters::create()->withQuery($query)->withHitsPerPage(999);
        $result = $this->client->search($parameters);

        return collect($result->getHits())
            ->map(fn ($hit) => [...$hit, 'reference' => $hit['id']]);
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
        $this->client->deleteDocument($document->id());
    }

    public function exists()
    {
        return $this->manager->indexExists($this->name);
    }

    protected function insertDocuments(Documents $documents)
    {
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
        $this->deleteIndex();
        $this->createIndex();

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
}
