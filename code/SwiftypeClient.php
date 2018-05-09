<?php

namespace Marcz\Swiftype;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Marcz\Swiftype\Jobs\JsonBulkExport;
use Marcz\Swiftype\Jobs\JsonExport;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\ArrayList;
use Marcz\Search\Config;
use Marcz\Search\Client\SearchClientAdaptor;
use Marcz\Swiftype\Jobs\DeleteRecord;
use Swiftypesearch\ClientBuilder;
use Marcz\Search\Client\DataWriter;
use Marcz\Search\Client\DataSearcher;

class SwiftypeClient implements SearchClientAdaptor, DataWriter, DataSearcher
{
    use Injectable, Configurable;

    protected $clientIndex;
    protected $clientIndexName;
    protected $clientAPI;
    protected $response;

    private static $batch_length = 100;

    public function createClient()
    {
        if ($this->clientAPI) {
            return $this->clientAPI;
        }

        $host = Environment::getEnv('SS_ELASTIC_HOST');
        $port = Environment::getEnv('SS_ELASTIC_PORT');
        $http = Environment::getEnv('SS_ELASTIC_HTTP');
        $user = Environment::getEnv('SS_ELASTIC_USER');
        $pass = Environment::getEnv('SS_ELASTIC_PASS');
        $cert = Environment::getEnv('SS_ELASTIC_CERT');

        $conf = [];
        if ($host) {
            $conf['host'] = $host;
        }
        if ($port) {
            $conf['port'] = $port;
        }
        if ($http) {
            $conf['scheme'] = $http;
        }
        if ($user) {
            $conf['user'] = $user;
        }
        if ($pass) {
            $conf['pass'] = $pass;
        }

        $builder = ClientBuilder::create()->setHosts([$conf]);

        if ($cert) {
            $builder = $builder->setSSLVerification($cert);
        }

        $this->clientAPI = $builder->build();

        return $this->clientAPI;
    }

    public function initIndex($indexName)
    {
        $client = $this->createClient();

        $this->clientIndex     = $client->indices();
        $this->clientIndexName = $indexName;

        return $this->clientIndex;
    }

    public function createIndex($indexName)
    {
        $index    = $this->initIndex($indexName);
        $settings = ['index' => strtolower($this->clientIndexName)];

        try {
            $index->create($settings);
        } catch (\Exception $exception) {
            $json = json_decode($exception->getMessage(), true);
            if (!$json['status'] === 400) {
                throw $exception;
            }

            $error = $json['error'];

            if ($error['type'] !== 'resource_already_exists_exception') {
                throw $exception;
            }
        }

        return $index;
    }

    public function update($data)
    {
        $indexName = strtolower($this->clientIndexName);
        $params    = [
            'index' => $indexName,
            'type'  => $indexName,
            'id'    => $data['ID'],
            'body'  => ['upsert' => $data, 'doc' => $data]
        ];

        $this->callClientMethod('update', [$params]);
    }

    public function bulkUpdate($list)
    {
        $indexName = strtolower($this->clientIndexName);
        $params    = [];
        for ($i = 0; $i < count($list); $i++) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_type'  => $indexName,
                    '_id'    => $list[$i]['ID']
                ]
            ];

            $params['body'][] = $list[$i];
        }

        $this->callClientMethod('bulk', [$params]);
    }

    public function deleteRecord($recordID)
    {
        $indexName = strtolower($this->clientIndexName);
        $params    = [
            'index' => $indexName,
            'type'  => $indexName,
            'id'    => $recordID,
        ];

        $this->callClientMethod('delete', [$params]);
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

    public function search($term = '*', $filters = [], $pageNumber = 0, $pageLength = 20)
    {
        $indexName = strtolower($this->clientIndexName);
        $term      = trim($term);
        $term      = empty($term) ? '*' : $term;
        $terms     = explode(' ', $term);

        // Add tilde for fuzziness on first word used for autocomplete
        if ($term !== '*' && count($terms) === 1) {
            $term = $term . '~';
        }

        $query     = [
            'index' => $indexName,
            'type'  => $indexName,
            'from'  => $pageNumber,
            'size'  => $pageLength,
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'query_string' => [
                                    'query'            => $term,
                                    'analyze_wildcard' => true,
                                    'default_field'    => '*',
                                    'fuzziness'        => 2,
                                ]
                            ],
                        ],
                        'filter'   => [],
                        'should'   => [],
                        'must_not' => [],
                    ],
                ],
            ],
        ];

        $facets    = $query['body']['query']['bool']['must'];
        $modifiers = $this->translateFilterModifiers($filters);

        if (isset($modifiers['facetFilters'])) {
            $facets = array_merge($facets, $modifiers['facetFilters']);

            $query['body']['query']['bool']['must'] = $facets;
        }

        if (isset($modifiers['filters'])) {
            $query['body']['query']['bool']['filter'] = $modifiers['filters'];
        }

        $response = $this->callClientMethod('search', [$query]);
        $total = (int) $response['hits']['total'];
        $this->response = ['_total' => $total] + $response;

        $hits = new ArrayList($this->response['hits']['hits']);
        if ($total) {
            $hits = new ArrayList($hits->column('_source'));
        }

        return $hits;
    }

    public function getResponse()
    {
        return $this->response;
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
                        $modifiedFilter = array_merge(
                            $modifiedFilter,
                            $this->modifyFilters($modifier, $fieldName, $value)
                        );
                    } else {
                        $modifiedFilter[] = $this->modifyFilter($modifier, $fieldName, $value);
                    }
                }
            }

            $query['filters'] = [
                'bool' => ['must' => $modifiedFilter]
            ];
        }

        if ($forFacets) {
            $query['facetFilters'] = [];

            foreach ($forFacets as $filterArray) {
                foreach ($filterArray as $key => $value) {
                    if (is_array($value)) {
                        $phrases = array_map(
                            function ($item) use ($key) {
                                return [
                                    'match_phrase' => [
                                        $key => ['query' => $item]
                                        ]
                                    ];
                            },
                            $value
                        );

                        $query['facetFilters'][] = [
                                'bool' => [
                                    'should'               => $phrases,
                                    'minimum_should_match' => 1
                                ]
                            ];
                    } else {
                        $query['facetFilters'][] = [
                            'match_phrase' => [
                                $key => ['query' => $value]
                            ]
                        ];
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

    public function callClientMethod($methodName, $parameters = [])
    {
        return call_user_func_array([$this->clientAPI, $methodName], $parameters);
    }

    public function modifyFilter($modifier, $key, $value)
    {
        return Injector::inst()->create('Marcz\\Swiftype\\Modifiers\\' . $modifier)->apply($key, $value);
    }

    public function modifyFilters($modifier, $key, $values)
    {
        $modifiedFilter = [];

        foreach ($values as $value) {
            $modifiedFilter[] = $this->modifyFilter($modifier, $key, $value);
        }

        return $modifiedFilter;
    }
}
