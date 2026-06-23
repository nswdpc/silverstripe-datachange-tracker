<?php

namespace Symbiote\DataChange\Extension;

use Symbiote\DataChange\Model\DataChangeRecord;
use Symbiote\DataChange\Service\DataChangeTrackService;
use SilverStripe\Forms\FieldList;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObject;

/**
 * Add to Pages you want changes recorded for
 *
 * @author  stephen@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class SiteTreeChangeRecordable extends ChangeRecordable
{

    public function onAfterPublish(&$original)
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            $this->dataChangeTrackService->track($record, 'Publish');
        }
    }

    public function onAfterUnpublish()
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            $this->dataChangeTrackService->track($record, 'Unpublish');
        }
    }

    public function updateCMSFields(FieldList $fields): ?\SilverStripe\Forms\FieldList
    {
        $record = $this->getOwner();
        if ($record instanceof DataObject && Permission::check('CMS_ACCESS_DataChangeAdmin')) {
            //Get all data changes relating to this page filter them by publish/unpublish
            $dataChanges = DataChangeRecord::get()->filter([
                'ChangeRecordID' => $record->ID,
                'ChangeRecordClass' => $record->ClassName
            ])->exclude('ChangeType', 'Change');

            //create a gridfield out of them
            $gridFieldConfig = GridFieldConfig_RecordViewer::create();
            $publishedGridField = GridField::create('PublishStates', 'Published States', $dataChanges, $gridFieldConfig);
            $dataColumns = $publishedGridField->getConfig()->getComponentByType(\SilverStripe\Forms\GridField\GridFieldDataColumns::class);
            $dataColumns->setDisplayFields([
                'ChangeType' => 'Change Type',
                'ObjectTitle' => 'Page Title',
                'ChangedBy.Title' => 'User',
                'Created' => 'Modification Date'
            ]);

            //linking through to the datachanges modeladmin
            $fields->addFieldToTab('Root.PublishedState', $publishedGridField);
        }

        return $fields;
    }
}
