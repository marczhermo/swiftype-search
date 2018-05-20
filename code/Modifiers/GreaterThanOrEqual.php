<?php

namespace Marcz\Swiftype\Modifiers;

use SilverStripe\Core\Injector\Injectable;
use Marcz\Search\Client\ModifyFilterable;

class GreaterThanOrEqual implements ModifyFilterable
{
    use Injectable;

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
