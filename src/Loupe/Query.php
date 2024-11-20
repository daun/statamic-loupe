<?php

namespace Daun\StatamicLoupe\Loupe;

use Statamic\Search\QueryBuilder;

class Query extends QueryBuilder
{
    public function getSearchResults($query)
    {
        return $this->index->lookup($query);
    }
}
