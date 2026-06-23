<?php

namespace Symbiote\DataChange\Extension;

use DateTime;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Config\Config;

/**
 * Add to classes you want to track specfic changes on
 *
 * @author  stephen@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 * @property ?string $LastSignificantChange
 * @property ?string $ChangeDescription
 * @extends \SilverStripe\Core\Extension<static>
 */
class SignificantChangeRecordable extends Extension
{

    private static array $ignored_fields = [];

    private static array $significant_fields = [];

    private static array $db = [
        'LastSignificantChange' => 'DBDatetime',
        'ChangeDescription' => 'Text'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('LastSignificantChange');
        $fields->removeByName('ChangeDescription');
        if ($this->getOwner()->LastSignificantChange !== null) {
            $dateTime = $this->getOwner()->dbObject('LastSignificantChange');
            //Put these fields on the top of the First Tab's form
            $fields->addFieldsToTab(
                'Root.SignificantChanges',
                [
                    ReadonlyField::create(
                        "InfoLastSignificantChange",
                        "Last Significant change was at: " . $dateTime->Nice()
                    ),
                    CheckboxField::create(
                        "ClearSignificantChange",
                        "Clear the last significant change"
                    )->setDescription(
                        'Check and save this Record again to clear the Last Significant change date.'
                    )->setValue(false),
                    TextField::create(
                        'ChangeDescription',
                        'Description of changes'
                    )->setDescription(
                        'This is an automatically generated list of changes to important fields.'
                    )
                ]
            );
        }
    }

    public function onBeforeWrite()
    {
        // Load the significant_fields and check to see if they have changed if they have record the current DateTime
        $significant = Config::inst()->get($this->getOwner()->Classname, 'significant_fields');
        $isSignificantChange = $this->getOwner()->ClearSignificantChange;

        if (isset($significant) && !$isSignificantChange) {
            $significant = array_combine($significant, $significant);
            //If the owner object or an extension of it implements getSignificantChange call it instead of testing here
            if ($this->getOwner()->hasMethod('getSignificantChange') && $this->getOwner()->getSignificantChange()) {
                //Set LastSignificantChange to now
                $this->getOwner()->LastSignificantChange = date(DateTime::ATOM);
            } else {
                $changes = $this->getOwner()->getChangedFields(true, 2);
                //A simple interesect of the keys gives us whether a change has occurred
                if (count($changes) && count(array_intersect_key($changes, $significant))) {
                    //Set LastSignificantChange to now
                    $this->getOwner()->LastSignificantChange = date(DateTime::ATOM);
                }
            }
            
            //If we don't have any significant changes leave the field alone as a previous edit may have been
            //significant.
        } elseif ($this->getOwner()->isInDB()) {
            $this->getOwner()->LastSignificantChange = null;
        }
    }
}
