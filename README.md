# Swiftype Search

[![Build Status](https://travis-ci.org/marczhermo/swiftype-search.svg?branch=master)](https://travis-ci.org/marczhermo/swiftype-search)
[![Latest Stable Version](https://poser.pugx.org/marczhermo/swiftype-search/v/stable)](https://packagist.org/packages/marczhermo/swiftype-search)
[![codecov](https://codecov.io/gh/marczhermo/swiftype-search/branch/master/graph/badge.svg)](https://codecov.io/gh/marczhermo/swiftype-search)
[![Total Downloads](https://poser.pugx.org/marczhermo/swiftype-search/downloads)](https://packagist.org/packages/marczhermo/swiftype-search)
[![License](https://poser.pugx.org/marczhermo/swiftype-search/license)](https://packagist.org/packages/marczhermo/swiftype-search)

## Overview

Uses the [Swiftype Site Search API](https://swiftype.com/documentation/site-search/overview) to push content as structured data to one or more Swiftype engines. Provides a client to search through the API.

For usage of the [Swiftype Site Search Crawler](https://swiftype.com/documentation/site-search/overview#engine_types), please use the [ichaber/silverstripe-swiftype](https://github.com/ichaber/silverstripe-swiftype) module instead.

## SilverStripe 3

Originally built for SS4, a rework has been done to make this module compatible with SilverStripe 3.
Please check this branch, https://github.com/marczhermo/swiftype-search/blob/3/README.md

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
