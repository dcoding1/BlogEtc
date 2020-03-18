<?php

namespace WebDevEtc\BlogEtc;

use Swis\LaravelFulltext\Search;
use Swis\LaravelFulltext\TermBuilder;
use Swis\LaravelFulltext\IndexedRecord;

class BlogSearch extends Search
{
    public function searchQuery($search)
    {
        $titleWeight = str_replace(',', '.', (float)config('laravel-fulltext.weight.title', 1.5));
        $contentWeight = str_replace(',', '.', (float)config('laravel-fulltext.weight.content', 1.0));

        $query = IndexedRecord::query()
            ->whereRaw('MATCH (indexed_title, indexed_content) AGAINST (?)', [$search])
            ->orderByRaw(
                '(' .$titleWeight. ' * (MATCH (indexed_title) AGAINST (?)) +
                ' . $contentWeight. ' * (MATCH (indexed_title, indexed_content) AGAINST (?))
                ) DESC', [$search, $search]
            )
            ->limit(config('laravel-fulltext.limit-results'))
            ->with('indexable')
        ;

        return $query;
    }
}
