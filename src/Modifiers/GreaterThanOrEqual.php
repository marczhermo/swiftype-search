<?php

namespace Marcz\Swiftype\Modifiers;

use Object;
use Marcz\Search\Client\ModifyFilterable;

class GreaterThanOrEqual extends Object implements ModifyFilterable
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
