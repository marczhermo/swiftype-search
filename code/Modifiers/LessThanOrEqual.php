<?php

namespace Marcz\Elastic\Modifiers;

use SilverStripe\Core\Injector\Injectable;
use Marcz\Search\Client\ModifyFilterable;

class LessThanOrEqual implements ModifyFilterable
{
    use Injectable;

    public function apply($key, $value)
    {
        return [
            'range' => [
                $key => ['lte' => $value]
            ]
        ];
    }
}
