<?php

namespace Marcz\Swiftype;

use Injector;
use QueuedJobService;
use Marcz\Swiftype\Jobs\JsonBulkExport;
use Marcz\Swiftype\Jobs\CrawlBulkExport;
use Marcz\Swiftype\Jobs\JsonExport;
use Marcz\Swiftype\Jobs\CrawlExport;
use Marcz\Swiftype\Jobs\DeleteRecord;
use Marcz\Swiftype\Jobs\CrawlDeleteRecord;
use DataList;
use ArrayList;
use Marcz\Search\Config as SearchConfig;
use Marcz\Search\Client\SearchClientAdaptor;
use GuzzleHttp\Ring\Client\CurlHandler;
use GuzzleHttp\Stream\Stream;
use Marcz\Search\Client\DataWriter;
use Marcz\Search\Client\DataSearcher;
use Marcz\Swiftype\Model\SwiftypeEngine;
use Exception;
use Config;
use Director;
use Versioned;

class SwiftypeClient extends SS_Object implements SearchClientAdaptor, DataWriter, DataSearcher
{
    protected $authToken;
    protected $clientIndexName;
    protected $clientIndexClass;
    protected $documentType;
    protected $domainId;
    protected $engineSlug;
    protected $engineKey;
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

        $indexConfig = ArrayList::create(SearchConfig::config()->get('indices'))
            ->find('name', $indexName);

        $this->clientIndexClass = $indexConfig['class'];

        if (!empty($indexConfig['domainId'])) {
            $this->domainId = $indexConfig['domainId'];
        }

        if (!empty($indexConfig['engineSlug'])) {
            $this->engineSlug = strtolower($indexConfig['engineSlug']);
        }

        if (!empty($indexConfig['engineKey'])) {
            $this->engineKey = $indexConfig['engineKey'];
        }

        if (!empty($indexConfig['documentType'])) {
            $this->documentType = $indexConfig['documentType'];
        } else {
            $this->documentType = strtolower($indexName);
        }

        $engine = SwiftypeEngine::get()->find('IndexName', $indexName);
        if ($engine) {
            $this->engineSlug = $engine->EngineSlug;
            $this->engineKey = $engine->EngineKey;
            $this->documentType = $engine->DocumentType;
            $domain = $engine->Domain()->first();
            if ($domain) {
                $this->domainId = $domain->DomainId;
            }
        }

        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $this->rawQuery = [
            'http_method'   => 'GET',
            'scheme'        => 'https',
            'uri'           => parse_url($endPoint, PHP_URL_PATH),
            'headers'       => [
                'host'  => [parse_url($endPoint, PHP_URL_HOST)],
                'Content-Type' => ['application/json'],
            ],
            'client' => [
                'timeout' => 60.0,
                'curl' => [
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => false
                ]
            ]
        ];

        $this->extend('updateInitIndex', $this->rawQuery);

        return $this->sql();
    }

    public function createIndex($indexName)
    {
        if (!$this->hasEngine($indexName)) {
            $this->createEngine($indexName);
        }

        $documentTypes = $this->getDocumentTypes($indexName);

        return $documentTypes ?: $this->createDocumentType($indexName, $indexName);
    }

    public function hasEngine($indexName)
    {
        $url = sprintf(
            '%sengines.json',
            parse_url($this->getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH)
        );

        $data = ['auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

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
            parse_url($this->getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH),
            strtolower($indexName)
        );

        $data = ['auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

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
            parse_url($this->getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH)
        );
        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
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

        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%sengines/%s/document_types.json',
            parse_url($endPoint, PHP_URL_PATH),
            strtolower($indexName)
        );
        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
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
        $rawQuery = $this->rawQuery;

        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/create_or_update.json',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName
        );
        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
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
        $rawQuery = $this->rawQuery;

        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/bulk_create_or_update_verbose',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName
        );
        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
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
        $rawQuery = $this->rawQuery;

        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $url = sprintf(
            '%1$sengines/%2$s/document_types/%2$s/documents/%3$s.json',
            parse_url($endPoint, PHP_URL_PATH),
            $indexName,
            $recordID
        );
        $data = ['auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')];

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
        $indexConfig = ArrayList::create(SearchConfig::config()->get('indices'))
                        ->find('name', $indexName);
        $exportClass = (empty($indexConfig['crawlBased'])) ? JsonBulkExport::class : CrawlBulkExport::class;

        $list        = new DataList($className);
        $total       = $list->count();
        $batchLength = self::config()->get('batch_length') ?: SearchConfig::config()->get('batch_length');
        $totalPages  = ceil($total / $batchLength);

        $this->initIndex($indexName);

        if ($exportClass === CrawlBulkExport::class) {
            $service = singleton(QueuedJobService::class);
            $job = Injector::inst()->createWithArgs(
                $exportClass,
                [$indexName, $className]
            );
            $jobId = $service->queueJob($job);
            $service->runJob($jobId);

            return;
        }

        for ($offset = 0; $offset < $totalPages; $offset++) {
            $job = Injector::inst()->createWithArgs(
                $exportClass,
                [$indexName, $className, $offset * $batchLength]
            );

            singleton(QueuedJobService::class)->queueJob($job);
        }
    }

    public function createExportJob($indexName, $className, $recordId)
    {
        $indexConfig = ArrayList::create(SearchConfig::config()->get('indices'))
                        ->find('name', $indexName);
        $exportClass = (empty($indexConfig['crawlBased'])) ? JsonExport::class : CrawlExport::class;

        $list   = new DataList($className);
        $record = $list->byID($recordId);

        if (!$record) {
            return;
        }

        if ($record->hasExtension(Versioned::class)) {
            $record = Versioned::get_by_stage(
                $className,
                'Live'
            )->byID($recordId);
            if (!$record) {
                return null;
            }
        }

        $job = Injector::inst()->createWithArgs(
            $exportClass,
            [$indexName, $className, $recordId]
        );

        $service = singleton(QueuedJobService::class);
        $jobId = $service->queueJob($job);

        // Runs the queue job immediately for crawl based approach.
        if ($exportClass === CrawlExport::class) {
            $service->runJob($jobId);
        }
    }

    public function createDeleteJob($indexName, $className, $recordId)
    {
        $indexConfig = ArrayList::create(SearchConfig::config()->get('indices'))
            ->find('name', $indexName);
        $exportClass = (empty($indexConfig['crawlBased'])) ? DeleteRecord::class : CrawlDeleteRecord::class;

        $job = Injector::inst()->createWithArgs(
            $exportClass,
            [$indexName, $className, $recordId]
        );

        $service = singleton(QueuedJobService::class);
        $jobId = $service->queueJob($job);

        // Runs the queue job immediately for crawl based approach.
        if ($exportClass === CrawlDeleteRecord::class) {
            $service->runJob($jobId);
        }
    }

    public function search($term = '', $filters = [], $pageNumber = 0, $pageLength = 20)
    {
        $term = trim($term);
        $indexName = strtolower($this->clientIndexName);
        $documentType = $this->documentType;
        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');
        $indexConfig = ArrayList::create(SearchConfig::config()->get('indices'))
            ->find('name', $this->clientIndexName);

        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'q' => $term,
            'document_types' => [$documentType],
            'page' => $pageNumber ?: 1,
            'per_page' => $pageLength,
            'filters' => [$documentType => $this->translateFilterModifiers($filters)],
            'facets' => [$documentType => []],
        ];

        $url = sprintf(
            '%s/engines/%s/search.json',
            parse_url($endPoint, PHP_URL_PATH),
            $this->engineSlug ?: $indexName
        );

        if ($this->engineKey) {
            $url = sprintf(
                '%s/public/engines/search.json',
                parse_url($endPoint, PHP_URL_PATH)
            );
            $data['engine_key'] = $this->engineKey;
            unset($data['auth_token']);
        }

        if (!empty($indexConfig['attributesForFaceting'])) {
            $data['facets'] = [$documentType => array_map('strtolower', $indexConfig['attributesForFaceting'])];
        }

        $this->extend('updateQueryData', $data);
        $this->rawQuery['uri'] = $url;
        $this->rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $handler = $this->clientAPI;
        $response = $handler($this->rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        if ($response->wait()['reason'] !== 'OK') {
            return new ArrayList([['Content' => $response->wait()['reason']]]);
        }

        $this->response = json_decode($response['body'], true);
        $this->response['_total'] = $this->response['info'][$documentType]['total_result_count'];
        $recordData = array_map(
            [$this, 'mapToDataObject'],
            $this->response['records'][$documentType]
        );

        return new ArrayList($recordData);
    }

    public function crawlURL($url)
    {
        $indexName = strtolower($this->clientIndexName);
        $endPoint = $this->getEnv('SS_SWIFTYPE_END_POINT');

        $data = [
            'auth_token' => $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN'),
            'url' => $url,
        ];

        $uri = sprintf(
            '%s/engines/%s/domains/%s/crawl_url.json',
            parse_url($endPoint, PHP_URL_PATH),
            $this->engineSlug ?: $indexName,
            $this->domainId
        );

        $this->rawQuery['http_method'] = 'PUT';
        $this->rawQuery['uri'] = $uri;
        $this->rawQuery['body'] = json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        $handler = $this->clientAPI;
        $response = $handler($this->rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        return in_array($response->wait()['reason'], ['OK', 'Created']);
    }

    public function crawlDomain()
    {
        $indexName = strtolower($this->clientIndexName);

        $uri = sprintf(
            '%s/engines/%s/domains/%s/recrawl.json?auth_token=%s',
            parse_url($this->getEnv('SS_SWIFTYPE_END_POINT'), PHP_URL_PATH),
            $this->engineSlug ?: $indexName,
            $this->domainId,
            $this->getEnv('SS_SWIFTYPE_AUTH_TOKEN')
        );

        $this->rawQuery['http_method'] = 'PUT';
        $this->rawQuery['uri'] = $uri;
        $this->rawQuery['headers']['Content-Length'] = [0];

        $handler = $this->clientAPI;
        $response = $handler($this->rawQuery);
        $stream = Stream::factory($response['body']);
        $response['body'] = $stream->getContents();

        if (in_array($response->wait()['reason'], ['OK', 'Created'])) {
            return $response->wait()['reason'];
        }

        $body = json_decode($response->wait()['body'] , true);

        return $body['error'];
    }

    public function mapToDataObject($record)
    {
        $className = empty($record['ClassName']) ? $this->clientIndexClass : $record['ClassName'];

        if (isset($record['title'])) {
            $record['Title'] = $record['title'];
            unset($record['title']);
        }

        if (isset($record['body'])) {
            $record['Content'] = $record['body'];
            unset($record['body']);
        }

        $this->extend('normaliseRecord', $record);

        return Injector::inst()->createWithArgs($className, [$record]);
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

    public function getEnv($name)
    {
        if (Director::isDev() && Director::is_cli() && $name == 'SS_SWIFTYPE_AUTH_TOKEN') {
            return 'SS_SWIFTYPE_AUTH_TOKEN';
        }
        // return Environment::getEnv($name);
        return constant($name);
    }
}
