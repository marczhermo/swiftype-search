<?php

namespace Marcz\Elastic\Modifiers;

use SilverStripe\Core\Injector\Injectable;
use Marcz\Search\Client\ModifyFilterable;

class LessThan implements ModifyFilterable
{
    use Injectable;

    public function apply($key, $value)
    {
        return [
            'range' => [
                $key => ['lt' => $value]
            ]
        ];
    }
}
