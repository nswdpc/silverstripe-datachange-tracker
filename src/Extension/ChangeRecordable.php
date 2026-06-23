<?php

namespace Symbiote\DataChange\Extension;

use Symbiote\DataChange\Service\DataChangeTrackService;
use Symbiote\DataChange\Model\DataChangeRecord;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 * Add to classes you want changes recorded for
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 * @extends \SilverStripe\Core\Extension<static>
 */
class ChangeRecordable extends Extension
{

    protected ?DataChangeTrackService $dataChangeTrackService = null;

    private static array $ignored_fields = [];

    protected bool $isNewObject = false;

    protected string $changeType = 'Change';

    public function setDataChangeTrackService(DataChangeTrackService $dataChangeTrackService)
    {
        $this->dataChangeTrackService = $dataChangeTrackService;
    }

    public function onBeforeWrite()
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            if ($record->isInDB()) {
                $this->dataChangeTrackService->track($record, $this->changeType);
            } else {
                $this->isNewObject = true;
                $this->changeType = 'New';
            }
        }
    }

    public function onAfterWrite()
    {
        $record = $this->getOwner();
        if ($record instanceof DataObject && $this->isNewObject) {
            $this->dataChangeTrackService->track($record, $this->changeType);
            $this->isNewObject = false;
        }
    }

    public function onBeforeDelete()
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            $this->dataChangeTrackService->track($record, 'Delete');
        }
    }

    public function getIgnoredFields(): ?array
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            $ignored = Config::inst()->get(ChangeRecordable::class, 'ignored_fields');
            $class = $record->ClassName;
            if (isset($ignored[$class])) {
                return array_combine($ignored[$class], $ignored[$class]);
            }
        }

        return null;
    }

    public function onBeforeVersionedPublish(string $from, string $to)
    {
        $record = $this->getOwner();
        if($record instanceof DataObject && $record->isInDB()) {
            $this->dataChangeTrackService->track($record, 'Publish ' . $from . ' to ' . $to);
        }
    }

    /**
     * Get the list of data changes for this item
     */
    public function getDataChangesList(): ?\SilverStripe\ORM\DataList
    {
        $record = $this->getOwner();
        if($record instanceof DataObject) {
            return DataChangeRecord::get()->filter([
                'ChangeRecordID' => $record->ID,
                'ChangeRecordClass' => $record->ClassName
            ]);
        } else {
            return null;
        }
    }
}
