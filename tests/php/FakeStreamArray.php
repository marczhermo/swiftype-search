<?php
namespace Marcz\Swiftype\Tests;

class FakeStreamArray
{
    protected $data;
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function __toString()
    {
        return json_encode($this->data);
    }
}
