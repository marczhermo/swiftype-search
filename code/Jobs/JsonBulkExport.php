<?php

namespace Marcz\Elastic\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\Assets\File;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Core\Config\Config as FileConfig;
use Marcz\Search\Processor\Exporter;
use Marcz\Elastic\ElasticClient;
use Exception;
use Marcz\Search\Config;

class JsonBulkExport extends AbstractQueuedJob implements QueuedJob
{
    protected $bulkArray = [];
    protected $client;

    /**
     * Methods that corresponds to the chronological steps for this job.
     * All methods must return true to signal successful process
     *
     * @var array
     */
    protected $definedSteps = [
        'stepCreateFile',
        'stepSendFile',
    ];

    /**
     * @param string $className
     * @param int $offset
     */
    public function __construct($indexName = null, $className = null, $offset = 0)
    {
        $this->totalSteps  = count($this->definedSteps);
        $this->currentStep = 0;
        $this->indexName   = $indexName;
        $this->className   = $className;
        $this->offset      = (int) $offset;
        $this->fileId      = 0;
        $this->bulk        = [];
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Json document export: "' . $this->className . '" starting at ' . $this->offset;
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
        if (!$this->className) {
            throw new Exception('Missing className defined on the constructor');
        }

        if (!isset($this->definedSteps[$this->currentStep])) {
            throw new Exception('User error, unknown step defined.');
        }

        $stepIsDone = call_user_func([$this, $this->definedSteps[$this->currentStep]]);

        if ($stepIsDone) {
            $this->currentStep++;
        }

        // and checking whether we're complete
        if ($this->currentStep >= $this->totalSteps) {
            $this->isComplete = true;
        }
    }

    public function stepCreateFile()
    {
        $this->addMessage('Step 1: Create File');

        $file     = new File();
        $exporter = Exporter::create();
        $dateTime = DBDatetime::now();
        $fileName = sprintf(
            '%s_export_%s_%d.json',
            $this->className,
            $dateTime->URLDatetime(),
            $this->offset
        );
        $batchLength = ElasticClient::config()->get('batch_length') ?: Config::config()->get('batch_length');

        $this->bulkArray = $exporter->bulkExport($this->className, $this->offset, $batchLength);

        FileConfig::modify()->set(File::class, 'allowed_extensions', ['json']);
        $file->setFromString(json_encode($this->bulkArray), $fileName);
        $file->write();
        $file->publishFile();

        $this->fileId = $file->ID;

        $this->addMessage('<p><a href="' . $file->getAbsoluteURL() . '" target="_blank">' . $fileName . '</a></p>');

        return true;
    }

    public function stepSendFile()
    {
        $this->addMessage('Step 2: Send File');

        $method    = $this->bulkArray ? 'updateByArray' : 'updateByFile';
        $parameter = $this->bulkArray ? $this->bulkArray : $this->fileId;

        call_user_func([$this, $method], $parameter);

        return true;
    }

    /**
     * Called when the job is determined to be 'complete'
     * Clean-up object properties
     */
    public function afterComplete()
    {
        $this->indexName = null;
        $this->className = null;
        $this->offset    = 0;
        $this->fileId    = 0;
        $this->bulk      = [];
    }

    public function createClient($client = null)
    {
        if (!$client) {
            $this->client = ElasticClient::create();
        }

        $this->client->initIndex($this->indexName);

        return $this->client;
    }

    public function updateByArray($bulkArray)
    {
        $this->createClient();
        $this->client->bulkUpdate($bulkArray);
    }

    public function updateByFile($fileId)
    {
        $file      = File::get()->byID($fileId);
        $bulkArray = json_decode($file->getString(), true);
        $this->updateByArray($bulkArray);
    }
}
