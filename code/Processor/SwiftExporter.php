<?php

namespace Marcz\Swiftype\Processor;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Extensible;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use Marcz\Search\Config;
use Marcz\Search\Processor\Exporter;
use SilverStripe\Versioned\Versioned;

class SwiftExporter extends Exporter
{
    use Injectable;
    use Extensible;

    protected $className;

    public function setClassName($className)
    {
        $this->className = $className;
    }

    public function export($dataObject, $clientClassName = null)
    {
        $dataClassName = get_class($dataObject);
        if ($dataObject->has_extension(Versioned::class)) {
            $dataObject = Versioned::get_by_stage(
                    $dataClassName,
                    Versioned::LIVE
                )->byID($dataObject->ID);
        }

        $hasOne   = $dataObject->config()->get('has_one');
        $hasMany  = $dataObject->config()->get('has_many');
        $manyMany = $dataObject->config()->get('many_many');

        $record = $dataObject->toMap();
        $fields = DataObject::getSchema()
            ->databaseFields($dataClassName, $aggregate = true);

        $document = [
            'external_id' => $record['ID'],
            'fields' => [],
        ];

        foreach ($record as $column => $value) {
            if (isset($fields[$column])) {
                $document['fields'][] = $this->translateSwiftypeSchema(
                    $column,
                    $value,
                    $fields[$column]
                );
            }
        }

        foreach ($hasOne as $column => $className) {
            $oneItem = $dataObject->{$column}();
            if ($oneItem instanceof File) {
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column . '_URL',
                    'value' => $oneItem->getAbsoluteURL()
                ];
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column . '_Title',
                    'value' => $oneItem->getTitle()
                ];
            } else {
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column,
                    'value' => $oneItem->getTitle()
                ];
            }
        }

        foreach ($hasMany as $column => $className) {
            $items = [];
            $collection = $dataObject->{$column}();
            foreach ($collection as $item) {
                $items[] = $item->getTitle();
            }
            if ($items) {
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column,
                    'value' => $items,
                ];
            }
        }

        foreach ($manyMany as $column => $className) {
            $items    = [];
            $contents = [];
            $collection = $dataObject->{$column}();

            foreach ($collection as $item) {
                $items[] = $item->getTitle();
                if (!empty($item->Content)) {
                    $contents[] = $item->Content;
                } elseif (!empty($item->HTML)) {
                    $contents[] = $item->HTML;
                }
            }

            if ($items) {
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column,
                    'value' => $items,
                ];
            }

            if ($contents) {
                $document['fields'][] = [
                    'type' => 'enum',
                    'name'  => $column . '_Content',
                    'value' => $contents,
                ];
            }
        }

        $this->extend('updateExport', $document, $clientClassName);
        $dataObject->destroy();

        return $document;
    }

    public function bulkExport($className, $startAt = 0, $max = 0, $clientClassName = null)
    {
        $list   = new DataList($className);
        $fields = DataObject::getSchema()
            ->databaseFields($className, $aggregate = true);
        if (isset($fields['ShowInSearch'])) {
            $list = $list->filter('ShowInSearch', true);
        }

        $total  = $list->count();
        $length = 20;
        $max    = $max ?: Config::config()->get('batch_length');
        $bulk   = [];
        $start  = $startAt;
        $pages  = $list->limit("$start,$length");
        $count  = 0;

        while ($pages) {
            foreach ($pages as $page) {
                if (!$page) {
                    break;
                }

                $bulk[] = $this->export($page, $clientClassName);
                $page->destroy();
                unset($page);
                $count++;
            }

            if ($pages->count() > ($length - 1)) {
                $start += $length;
                $pages = $list->limit("$start,$length");
            } else {
                break;
            }

            if ($max && $max > 0 && count($bulk) >= $max) {
                break;
            }
        }

        return $bulk;
    }

    protected function translateSwiftypeSchema($column, $value, $fieldType, $searchableAttributes = [])
    {
        $schema = ['type' => 'enum', 'name'  => $column, 'value' => $value];

        if ($column === 'ID' || in_array($fieldType, ['PrimaryKey', 'ForeignKey'])) {
            $schema['type'] = 'integer';
            $schema['value'] = (int) $value;

            return $schema;
        }

        if (strpos($fieldType, 'HTML') !== false || $column === 'Content') {
            $schema['type'] = 'text';

            return $schema;
        }

        $stringTypes = ['Name', 'Title'] + $searchableAttributes;
        if (in_array($column, $stringTypes)) {
            $schema['type'] = 'string';

            return $schema;
        }

        if (strpos($fieldType, 'Int') === 0) {
            $schema['type'] = 'integer';
            $schema['value'] = (int) $value;

            return $schema;
        }

        if (strpos($fieldType, 'Decimal') !== false
            || strpos($fieldType, 'Currency') !== false) {
            $schema['type'] = 'float';
            $schema['value'] = (float) $value;

            return $schema;
        }

        if (strpos($fieldType, 'Date') !== false) {
            $schema['type'] = 'date';
            $dateObject = new \DateTime($schema['value']);
            $schema['value'] = $dateObject->format('c');

            return $schema;
        }

        $field = Injector::inst()->create($fieldType);
        $formField = $field->scaffoldFormField();

        if ($formField instanceof UploadField) {
            $schema['type'] = 'integer';
            $schema['value'] = (int) $value;

            return $schema;
        }

        return $schema;
    }
}
