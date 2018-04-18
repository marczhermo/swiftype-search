<?php

namespace Marcz\Elastic\Modifiers;

use SilverStripe\Core\Injector\Injectable;

class LessThan implements ModifyFilterable
{
    use Injectable;

    public function apply($key, $value)
    {
        return sprintf('%s < %s', $key, $value);
    }
}
