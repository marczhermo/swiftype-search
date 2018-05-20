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
use GuzzleHttp\Ring\Client\CurlHandler;
use GuzzleHttp\Stream\Stream;
use Marcz\Search\Client\DataWriter;
use Marcz\Search\Client\DataSearcher;
use Exception;

class SwiftypeClient implements SearchClientAdaptor, DataWriter, DataSearcher
{
    use Injectable, Configurable;

    protected $authToken;
    protected $clientIndexName;
    protected $clientAPI;
    protected $response;
    protected $rawQuery;

    private static $batch_length = 100;

    public function createClient()
    {
        if ($this->clientAPI) {
            return $this->clientAPI;
        }

        return $this->setClientAPI(new CurlHandler());
    }

    public function setClientAPI($handler)
    {
        $this->clientAPI = $handler;

        return $this->clientAPI;
    }

    public function initIndex($indexName)
    {
        $this->createClient();

        $this->clientIndexName = $indexName;

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $this->rawQuery = [
            'http_method'   => 'GET',
            'uri'           => parse_url($endPoint, PHP_URL_PATH),
            //'query_string' => ($query) ? $query . '&' . $token : $token,
            'headers'       => [
                'host'  => [parse_url($endPoint, PHP_URL_HOST)],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ]
            // 'body' => $data
        ];

        return $this->sql();
    }

    public function createIndex($indexName)
    {
        if (!$this->hasEngine($indexName)) {
            $this->createEngine($indexName);
        }

        $documentTypes = $this->getDocumentTypes($indexName);

        if ($documentTypes) {
            $types = new ArrayList($documentTypes);
            if ($types->find('name', strtolower($indexName))) {
                return true;
            }
        }

        return $this->createDocumentType($indexName, $indexName);
    }

    public function hasEngine($indexName)
    {
        $url = sprintf(
            '%sengines.json',
            parse_url(Environment::getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH)
        );

        $data = ['auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

        $rawQuery = $this->initIndex($indexName);
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();
        $body = new ArrayList(json_decode($response['body'], true));

        return (bool) $body->find('name', strtolower($indexName));
    }

    public function getDocumentTypes($indexName)
    {
        $url = sprintf(
            '%sengines/%s/document_types.json',
            parse_url(Environment::getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH),
            strtolower($indexName)
        );

        $data = ['auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

        $rawQuery = $this->initIndex($indexName);
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        return json_decode($response['body'], true);
    }

    public function createEngine($indexName)
    {
        $rawQuery = $this->initIndex($indexName);
        $url = sprintf(
            '%sengines.json',
            parse_url(Environment::getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH)
        );
        $data = [
            'auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'engine' => ['name' => strtolower($indexName)],
        ];

        $rawQuery['http_method'] = 'POST';
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);

        return isset($response['status']) && 200 === $response['status'];
    }

    public function createDocumentType($type, $indexName)
    {
        $rawQuery = $this->initIndex($indexName);

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%sengines/%s/document_types.json',
            parse_url($endPoint, PHP_URL_PATH),
            strtolower($indexName)
        );
        $data = [
            'auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'document_type' => ['name' => strtolower($type)],
        ];

        $rawQuery['http_method'] = 'POST';
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);

        return isset($response['status']) && 200 === $response['status'];
    }

    public function update($data)
    {
        $indexName = strtolower($this->clientIndexName);
        $rawQuery = $this->initIndex($this->clientIndexName);

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/create_or_update.json',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName
        );
        $data = [
            'auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'document' => $data,
        ];

        $rawQuery['http_method'] = 'POST';
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        return isset($response['status']) && 200 === $response['status'];
    }

    public function bulkUpdate($list)
    {
        $indexName = strtolower($this->clientIndexName);
        $rawQuery = $this->initIndex($this->clientIndexName);

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/bulk_create_or_update_verbose',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName
        );
        $data = [
            'auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'documents' => $list,
        ];

        $rawQuery['http_method'] = 'POST';
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        return isset($response['status']) && 200 === $response['status'];
    }

    public function deleteRecord($recordID)
    {
        $indexName = strtolower($this->clientIndexName);
        $rawQuery = $this->initIndex($this->clientIndexName);

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/%3$s.json',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName,
            $recordID
        );
        $data = ['auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

        $rawQuery['http_method'] = 'DELETE';
        $rawQuery['uri'] = $url;
        $rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $this->rawQuery = $rawQuery;

        $handler = $this->clientAPI;
        $response = $handler($rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        return isset($response['status']) && in_array($response['status'], [204, 404]);
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
        $term = trim($term);
        $indexName = strtolower($this->clientIndexName);

        $this->rawQuery = $this->initIndex($this->clientIndexName);

        $endPoint = Environment::getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%sengines/%s/search.json',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName
        );
        $data = [
            'auth_token' => Environment::getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'q' => $term,
            'document_types' => [$indexName],
            'page' => 1 + $pageNumber,
            'per_page' => $pageLength,
            'filters' => [$indexName => $this->translateFilterModifiers($filters)],
            'facets' => [$indexName => []],
        ];

        $indexConfig = ArrayList::create(Config::config()->get('indices'))
                        ->find('name', $this->clientIndexName);

        if (!empty($indexConfig['attributesForFaceting'])) {
            $data['facets'] = [$indexName => $indexConfig['attributesForFaceting']];
        }

        $this->rawQuery['uri'] = $url;
        $this->rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $handler = $this->clientAPI;
        $response = $handler($this->rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        $this->response = json_decode($response['body'], true);
        $this->response['_total'] = $this->response['record_count'];

        return new ArrayList($this->response['records'][$indexName]);
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

            foreach ($modifiedFilter as $filter) {
                $column = key($filter);
                $previous = isset($query[$column]) ? $query[$column] : [];
                $query[$column] = array_merge($previous, current($filter));
            }
        }

        if ($forFacets) {
            foreach ($forFacets as $filterArray) {
                foreach ($filterArray as $key => $value) {
                    if (is_array($value)) {
                        $query[$key] = array_values($value);
                    } else {
                        $query[$key] = $value;
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

    public function sql()
    {
        return $this->rawQuery;
    }
}
