<?php

namespace Marcz\Elastic;

use SilverStripe\Core\Injector\Injectable;
use ElasticSearch\Client;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Marcz\Elastic\Jobs\JsonBulkExport;
use Marcz\Elastic\Jobs\JsonExport;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use Marcz\Search\Config;
use Marcz\Search\Client\SearchClientAdaptor;
use Marcz\Elastic\Jobs\DeleteRecord;

class ElasticClient implements SearchClientAdaptor
{
    use Injectable, Configurable;

    protected $clientIndex;
    protected $clientAPI;

    private static $batch_length = 100;

    public function createClient()
    {
        if (!$this->clientAPI) {
            $this->clientAPI = new Client(
                Environment::getEnv('SS_ALGOLIA_APP_NAME'),
                Environment::getEnv('SS_ALGOLIA_SEARCH_KEY')
            );
        }

        return $this->clientAPI;
    }

    public function initIndex($indexName)
    {
        $client = $this->createClient();

        $this->clientIndex = $client->initIndex($indexName);

        return $this->clientIndex;
    }

    public function createIndex($indexName)
    {
        $index    = $this->initIndex($indexName);
        $settings = [
            //Phrase query, surrounded by quotes eg. "search engine"
            'advancedSyntax' => true,
            'ranking'        => [
                'typo',
                'geo',
                'words',
                'filters',
                'proximity',
                'attribute',
                'exact',
                'custom'
            ]
        ];

        $indexConfig = ArrayList::create(Config::config()->get('indices'))
                            ->filter(['name' => $indexName])->first();

        if (!empty($indexConfig['ranking'])) {
            $settings['ranking'] = $indexConfig['ranking'];
        }

        if (!empty($indexConfig['searchableAttributes'])) {
            $settings['searchableAttributes'] = $indexConfig['searchableAttributes'];
        }

        if (!empty($indexConfig['attributesForFaceting'])) {
            $settings['attributesForFaceting'] = $indexConfig['attributesForFaceting'];
        }

        if (!empty($indexConfig['customRanking'])) {
            $settings['customRanking'] = $indexConfig['customRanking'];
        }

        // Set the default ranking
        $index->setSettings($settings);

        return $index;
    }

    public function update($data)
    {
        $this->clientIndex->saveObject($data, 'ID');
    }

    public function bulkUpdate($list)
    {
        $this->clientIndex->saveObjects($list, 'ID');
    }

    public function deleteRecord($recordID)
    {
        $this->clientIndex->deleteObject($recordID);
    }

    public function createBulkExportJob($indexName, $className)
    {
        $list        = new DataList($className);
        $total       = $list->count();
        $batchLength = self::config()->get('batch_length') ?: Config::config()->get('batch_length');
        $totalPages  = ceil($total / $batchLength);

        $this->initIndex($indexName);

        for ($offset = 0; $offset < $totalPages; $offset++) {
            $job = Injector::inst()->createWithArgs(
                    JsonBulkExport::class,
                    [$indexName, $className, $offset * $batchLength]
                );

            singleton(QueuedJobService::class)->queueJob($job);
        }
    }

    public function createExportJob($indexName, $className, $recordId)
    {
        $job = Injector::inst()->createWithArgs(
                JsonExport::class,
                [$indexName, $className, $recordId]
            );

        singleton(QueuedJobService::class)->queueJob($job);
    }

    public function createDeleteJob($indexName, $className, $recordId)
    {
        $job = Injector::inst()->createWithArgs(
                DeleteRecord::class,
                [$indexName, $className, $recordId]
            );

        singleton(QueuedJobService::class)->queueJob($job);
    }

    public function search($term = '', $filters = [], $pageNumber = 0, $pageLength = 20)
    {
        $query = [
            'page'       => $pageNumber,
            'hitsPerPage'=> $pageLength,
            // In order to retrieve facets and their respective counts as part of the JSON response
            'facets'     => ['*'],
        ];

        $query = array_merge($query, $this->translateFilterModifiers($filters));

        return $this->callIndexMethod('search', [$term, $query]);
    }

    /**
     * Modifies filters
     * @todo Refactor when unit tests is in place.
     * @param array $filters
     * @return array
     */
    public function translateFilterModifiers($filters = [])
    {
        $query       = [];
        $forFilters  = [];
        $forFacets   = [];

        foreach ($filters as $filterArray) {
            foreach ($filterArray as $key => $value) {
                $hasModifier = strpos($key, ':') !== false;
                if ($hasModifier) {
                    $forFilters[][$key] = $value;
                } else {
                    $forFacets[][$key] = $value;
                }
            }
        }

        if ($forFilters) {
            $query['filters'] = [];
            $modifiedFilter   = [];

            foreach ($forFilters as $filterArray) {
                foreach ($filterArray as $key => $value) {
                    $fieldArgs = explode(':', $key);
                    $fieldName = array_shift($fieldArgs);
                    $modifier  = array_shift($fieldArgs);
                    if (is_array($value)) {
                        $modifiedFilter[] = $this->modifyOrFilter($modifier, $fieldName, $value);
                    } else {
                        $modifiedFilter[] = $this->modifyFilter($modifier, $fieldName, $value);
                    }
                }
            }

            $query['filters'] = implode(' AND ', $modifiedFilter);
        }

        if ($forFacets) {
            $query['facetFilters'] = [];

            foreach ($forFacets as $filterArray) {
                foreach ($filterArray as $key => $value) {
                    if (is_array($value)) {
                        $query['facetFilters'][] = array_map(
                            function ($item) use ($key) {
                                return "{$key}:{$item}";
                            },
                            $value
                        );
                    } else {
                        $query['facetFilters'][] = ["{$key}:{$value}"];
                    }
                }
            }
        }

        return $query;
    }

    public function callIndexMethod($methodName, $parameters = [])
    {
        return call_user_func_array([$this->clientIndex, $methodName], $parameters);
    }

    public function modifyFilter($modifier, $key, $value)
    {
        return Injector::inst()->create('Marcz\\Elastic\\Modifiers\\' . $modifier)->apply($key, $value);
    }

    public function modifyOrFilter($modifier, $key, $values)
    {
        $modifiedFilter = [];

        foreach ($values as $value) {
            $modifiedFilter[] = $this->modifyFilter($modifier, $key, $value);
        }

        return '(' . implode(' OR ', $modifiedFilter) . ')';
    }
}
