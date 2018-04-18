<?php

namespace Marcz\Elastic\Extensions;

use SilverStripe\Core\Extension;

class Exporter extends Extension
{
    public function updateExport(&$data)
    {
        //Elastic Free Plan 10KB Limit Per Record
        $nineKB = 1024 * 9; // 1KB for other columns
        if (strlen($data['Content']) > $nineKB) {
            $data['Content'] = substr($data['Content'], 0, $nineKB);
        }
    }
}
