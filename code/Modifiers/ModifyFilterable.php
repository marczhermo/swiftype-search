<?php

namespace Marcz\Elastic\Modifiers;

interface ModifyFilterable
{
    public function apply($key, $value);
}
