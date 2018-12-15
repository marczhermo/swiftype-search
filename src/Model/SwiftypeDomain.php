<?php

namespace Marcz\Swiftype\Model;

use DataObject;
use DropdownField;

class SwiftypeDomain extends DataObject
{
    private static $db = [
        'Domain' => 'Varchar(255)',
        'DomainId' => 'Varchar(255)',
    ];

    private static $has_one = ['Engine' => 'Marcz\Swiftype\Model\SwiftypeEngine'];

    private static $singular_name = 'Domain';

    private static $plural_name = 'Domains';

    private static $summary_fields = [
        'ID' => 'ID',
        'Domain' => 'Domain',
        'DomainId' => 'Domain Id',
        'Engine.EngineSlug' => 'Engine Slug',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeFieldsFromTab('Root.Main', ['EngineID']);

        $engineSelect = DropdownField::create('EngineID', 'Engine', SwiftypeEngine::get()->map('ID', 'EngineSlug'));

        $fields->addFieldToTab('Root.Main', $engineSelect);

        return $fields;
    }
}