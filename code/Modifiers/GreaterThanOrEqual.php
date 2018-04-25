<?php

namespace Marcz\Elastic\Modifiers;

use SilverStripe\Core\Injector\Injectable;
use Marcz\Search\Client\ModifyFilterable;

class GreaterThanOrEqual implements ModifyFilterable
{
    use Injectable;

    public function apply($key, $value)
    {
        return [
            'range' => [
                $key => ['gte' => $value]
            ]
        ];
    }
}
