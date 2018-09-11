# Swiftype Search

[![Build Status](https://travis-ci.org/marczhermo/swiftype-search.svg?branch=master)](https://travis-ci.org/marczhermo/swiftype-search)
[![Latest Stable Version](https://poser.pugx.org/marczhermo/swiftype-search/v/stable)](https://packagist.org/packages/marczhermo/swiftype-search)
[![codecov](https://codecov.io/gh/marczhermo/swiftype-search/branch/master/graph/badge.svg)](https://codecov.io/gh/marczhermo/swiftype-search)
[![Total Downloads](https://poser.pugx.org/marczhermo/swiftype-search/downloads)](https://packagist.org/packages/marczhermo/swiftype-search)
[![License](https://poser.pugx.org/marczhermo/swiftype-search/license)](https://packagist.org/packages/marczhermo/swiftype-search)

## Overview

This is a client module which connects to Swiftype API for sending configuration, data model and fetching.

## Usage

The example below uses the 'Swiftype' client as the 3rd parameter on the createSearch() method.

````
// Controller method using the module's interface
$properties = $this->createSearch(
    $request->getVar('Keywords'), 'Properties', 'Swiftype'
);
$properties->filter([
    'AvailableStart:LessThanOrEqual' => $startDate,
    'AvailableEnd:GreaterThanOrEqual' => $endDate
]);

return ['Results' =>$properties->fetch()]; // ArrayList
````

## Installation

SilverStripe 3

````
composer require marczhermo/swiftype-search:^0.1
````

SilverStripe 4

````
composer require marczhermo/swiftype-search
````

## Requirements
This module needs the following packages:
````
"marczhermo/search-list": "^0.1.0",
"guzzlehttp/ringphp": "^1.1",
"silverstripe/queuedjobs": "^2"
````

## Versioning

This library follows [Semver](http://semver.org). According to Semver,
you will be able to upgrade to any minor or patch version of this library
without any breaking changes to the public API. Semver also requires that
we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All
other methods are not part of the public API. Where possible, we'll try
to keep `protected` methods backwards-compatible in minor/patch versions,
but if you're overriding methods then please test your work before upgrading.

## Reporting Issues

Please [create an issue](https://github.com/marczhermo/silverstripe-sscounter/issues)
for any bugs you've found, or features you're missing.

## License

This module is released under the [BSD 3-Clause License](LICENSE)
