<?php

namespace Marcz\Elastic\Extensions;

use SilverStripe\Core\Extension;
use Marcz\Elastic\ElasticClient;

class Exporter extends Extension
{
    public function updateExport(&$data, &$clientClassName)
    {
        if ($clientClassName === ElasticClient::class) {
            // TODO:
        }
    }
}
