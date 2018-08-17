<?php

namespace Marcz\Swiftype\Extensions;

use SilverStripe\Core\Extension;
use Marcz\Swiftype\SwiftypeClient;

class Exporter extends Extension
{
    public function updateExport(&$data, &$clientClassName)
    {
        if ($clientClassName === SwiftypeClient::class) {
            // TODO:
        }
    }
}
