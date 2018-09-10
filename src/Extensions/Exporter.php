<?php

namespace Marcz\Swiftype\Extensions;

use Extension;
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
