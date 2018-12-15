<?php

namespace Marcz\Swiftype\Extensions;

use FieldList;
use GridField;
use Extension;
use GridFieldConfig_RecordEditor;
use Marcz\Swiftype\Model\SwiftypeEngine;
use Marcz\Swiftype\Model\SwiftypeDomain;

class SiteConfig extends Extension
{
    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            'Root.Swiftype.Engines',
            GridField::create(
                'SwiftypeEngine',
                'Swiftype Engines',
                SwiftypeEngine::get(),
                GridFieldConfig_RecordEditor::create()
            )
        );

        $fields->addFieldToTab(
            'Root.Swiftype.Domains',
            GridField::create(
                'SwiftypeDomain',
                'Swiftype Domains',
                SwiftypeDomain::get(),
                GridFieldConfig_RecordEditor::create()
            )
        );
    }
}
