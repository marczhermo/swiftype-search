<?php

namespace Marcz\Swiftype\Modifiers;

use Marcz\Search\Client\ModifyFilterable;

class GreaterThan extends SS_Object implements ModifyFilterable
{
    public function apply($key, $value)
    {
        return [
            $key => [
                'type' => 'range',
                'from' => $value
            ]
        ];
    }
}
