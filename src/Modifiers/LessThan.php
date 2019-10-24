<?php

namespace Marcz\Swiftype\Modifiers;

use SS_Object;
use Marcz\Search\Client\ModifyFilterable;

class LessThan extends SS_Object implements ModifyFilterable
{
    public function apply($key, $value)
    {
        return [
            $key => [
                'type' => 'range',
                'to' => $value
            ]
        ];
    }
}
