<?php

namespace Marcz\Elastic;

use SilverStripe\Core\Extension;
use SilverStripe\View\Requirements;

class ElasticAdminExtension extends Extension
{
    public function init()
    {
        Requirements::javascript('marczhermo/elastic-search: client/dist/js/bundle.js');
        Requirements::css('marczhermo/elastic-search: client/dist/styles/bundle.css');
    }
}
