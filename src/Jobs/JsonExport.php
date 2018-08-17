<?php

namespace Marcz\Swiftype\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Marcz\Swiftype\Processor\SwiftExporter;
use Marcz\Swiftype\SwiftypeClient;
use Exception;
use SilverStripe\ORM\DataList;

class JsonExport extends AbstractQueuedJob implements QueuedJob
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
        return 'Json document export: "' . $this->className . '" with ID ' . $this->recordID;
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

        $list   = new DataList($this->className);
        $record = $list->byID($this->recordID);

        if (!$record) {
            throw new Exception('Record not found.');
        }

        $exporter = SwiftExporter::create();
        $client   = $this->createClient();
        $clientClassName = SwiftypeClient::class;
        $client->update(
            $exporter->export($record, $clientClassName)
        );

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
