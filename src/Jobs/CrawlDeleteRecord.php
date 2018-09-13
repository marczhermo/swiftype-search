<?php

namespace Marcz\Swiftype\Jobs;

use AbstractQueuedJob;
use QueuedJob;
use Marcz\Swiftype\Processor\SwiftExporter;
use Marcz\Swiftype\SwiftypeClient;
use Exception;
use DataList;

class CrawlDeleteRecord extends AbstractQueuedJob implements QueuedJob
{
    protected $client;

    /**
     * @param string $className
     * @param int $recordID
     */
    public function __construct($indexName = null, $className = null, $recordID = 0)
    {
        $this->indexName = $indexName;
        $this->className = $className;
        $this->recordID  = (int) $recordID;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Crawl delete document: "' . $this->className . '" with ID ' . $this->recordID;
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        if (!$this->indexName) {
            throw new Exception('Missing indexName defined on the constructor');
        }

        if (!$this->className) {
            throw new Exception('Missing className defined on the constructor');
        }

        if (!$this->recordID) {
            throw new Exception('Missing recordID defined on the constructor');
        }

        $this->addMessage('Todo: Implement crawling feature.');
        $this->isComplete = true;
    }

    /**
     * Called when the job is determined to be 'complete'
     * Clean-up object properties
     */
    public function afterComplete()
    {
        $this->indexName = null;
        $this->className = null;
        $this->recordID  = 0;
    }

    public function createClient($client = null)
    {
        if (!$client) {
            $this->client = SwiftypeClient::create();
        }

        $this->client->initIndex($this->indexName);

        return $this->client;
    }
}
