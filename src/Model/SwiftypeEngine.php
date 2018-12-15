<?php

namespace Marcz\Swiftype\Model;

use DataObject;

class SwiftypeEngine extends DataObject
{
    private static $db = [
        'IndexName' => 'Varchar(255)',
        'EngineSlug' => 'Varchar(255)',
        'EngineKey' => 'Varchar(255)',
        'DocumentType' => 'Varchar(255)',
    ];

    private static $has_many = ['Domain' => 'Marcz\Swiftype\Model\SwiftypeDomain'];

    private static $singular_name = 'Engine';

    private static $plural_name = 'Engines';

    private static $summary_fields = ['ID', 'EngineSlug', 'EngineKey', 'IndexName', 'DocumentType'];
}