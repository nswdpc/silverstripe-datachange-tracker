<?php

namespace Symbiote\DataChange\Extension;

use Symbiote\DataChange\Service\DataChangeTrackService;
use Symbiote\DataChange\Model\DataChangeRecord;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Config\Config;

/**
 * Add to classes you want changes recorded for
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 * @extends \SilverStripe\Core\Extension<static>
 */
class ChangeRecordable extends Extension
{

    /**
     *
     * @var DataChangeTrackService
     */
    public $dataChangeTrackService;

    private static array $ignored_fields = [];

    protected $isNewObject = false;

    protected $changeType = 'Change';

    public function onBeforeWrite()
    {
        if ($this->getOwner()->isInDB()) {
            $this->dataChangeTrackService->track($this->getOwner(), $this->changeType);
        } else {
            $this->isNewObject = true;
            $this->changeType = 'New';
        }
    }

    public function onAfterWrite()
    {
        if ($this->isNewObject) {
            $this->dataChangeTrackService->track($this->getOwner(), $this->changeType);
            $this->isNewObject = false;
        }
    }

    public function onBeforeDelete()
    {
        $this->dataChangeTrackService->track($this->getOwner(), 'Delete');
    }

    public function getIgnoredFields(): ?array
    {
        $ignored = Config::inst()->get(ChangeRecordable::class, 'ignored_fields');
        $class = $this->getOwner()->ClassName;
        if (isset($ignored[$class])) {
            return array_combine($ignored[$class], $ignored[$class]);
        }
        return null;
    }

    public function onBeforeVersionedPublish(string $from, string $to)
    {
        if ($this->getOwner()->isInDB()) {
            $this->dataChangeTrackService->track($this->getOwner(), 'Publish ' . $from . ' to ' . $to);
        }
    }

    /**
     * Get the list of data changes for this item
     */
    public function getDataChangesList(): \SilverStripe\ORM\DataList
    {
        return DataChangeRecord::get()->filter([
            'ChangeRecordID' => $this->getOwner()->ID,
            'ChangeRecordClass' => $this->getOwner()->ClassName
        ]);
    }
}
