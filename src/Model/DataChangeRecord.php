<?php

namespace Symbiote\DataChange\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\DataDifferencer;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;

/**
 * Record a change to a dataobject; use this to track data changes of objects
 *
 * @author  marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 * @property ?string $ChangeType
 * @property ?string $ObjectTitle
 * @property ?string $Before
 * @property ?string $After
 * @property ?string $Stage
 * @property ?string $CurrentEmail
 * @property ?string $CurrentURL
 * @property ?string $Referer
 * @property ?string $RemoteIP
 * @property ?string $Agent
 * @property ?string $GetVars
 * @property ?string $PostVars
 * @property int $ChangedByID
 * @property int $ChangeRecordID
 * @method \SilverStripe\Security\Member ChangedBy()
 * @method \SilverStripe\ORM\DataObject ChangeRecord()
 */
class DataChangeRecord extends DataObject
{
    private static string $table_name = 'DataChangeRecord';

    private static array $db = [
        'ChangeType' => 'Varchar',
        'ObjectTitle' => 'Varchar(255)',
        'Before' => 'Text',
        'After' => 'Text',
        'Stage' => 'Text',
        'CurrentEmail' => 'Text',
        'CurrentURL' => 'Varchar(255)',
        'Referer' => 'Varchar(255)',
        'RemoteIP' => 'Varchar(128)',
        'Agent' => 'Varchar(255)',
        'GetVars' => 'Text',
        'PostVars' => 'Text',
    ];

    private static array $has_one = [
        'ChangedBy' => Member::class,
        'ChangeRecord' => DataObject::class
    ];

    private static array $summary_fields    = [
        'ChangeType' => 'Change Type',
        'ChangeRecordClass' => 'Record Class',
        'ChangeRecordID' => 'Record ID',
        'ObjectTitle' => 'Record Title',
        'ChangedBy.Title' => 'User',
        'Created' => 'Modification Date'
    ];

    private static array $searchable_fields = [
        'ChangeType',
        'ObjectTitle',
        'ChangeRecordClass',
        'ChangeRecordID'
    ];

    private static string $default_sort      = 'ID DESC';

    private static array $indexes = [
        'Created' => true
    ];

    private static bool $save_request_vars      = false;

    private static array $field_blacklist        = ['Password'];

    private static array $request_vars_blacklist = ['url', 'SecurityID'];

    #[\Override]
    public function getCMSFields($params = null)
    {
        Requirements::css('symbiote/silverstripe-datachange-tracker: client/css/datachange-tracker.css');

        $fields = FieldList::create(
            ToggleCompositeField::create(
                'Details',
                'Details',
                [
                    ReadonlyField::create('ChangeType', 'Type of change'),
                    ReadonlyField::create('ChangeRecordClass', 'Record Class'),
                    ReadonlyField::create('ChangeRecordID', 'Record ID'),
                    ReadonlyField::create('ObjectTitle', 'Record Title'),
                    ReadonlyField::create('Created', 'Modification Date'),
                    ReadonlyField::create('Stage', 'Stage'),
                    ReadonlyField::create('User', 'User', $this->getMemberDetails()),
                    ReadonlyField::create('CurrentURL', 'URL'),
                    ReadonlyField::create('Referer', 'Referer'),
                    ReadonlyField::create('RemoteIP', 'Remote IP'),
                    ReadonlyField::create('Agent', 'Agent')
                ]
            )->setStartClosed(false)->addExtraClass('datachange-field'),
            ToggleCompositeField::create(
                'RawData',
                'Raw Data',
                [
                    ReadonlyField::create('Before'),
                    ReadonlyField::create('After'),
                    ReadonlyField::create('GetVars'),
                    ReadonlyField::create('PostVars')
                ]
            )->setStartClosed(false)->addExtraClass('datachange-field')
        );

        if (strlen($this->Before) && strlen($this->ChangeRecordClass) && class_exists($this->ChangeRecordClass)) {
            $before = Injector::inst()->create($this->ChangeRecordClass, $this->prepareForDataDifferencer($this->Before), true);
            $after  = Injector::inst()->create($this->ChangeRecordClass, $this->prepareForDataDifferencer($this->After), true);
            $diff   = DataDifferencer::create($before, $after);

            // The solr search service injector dependency causes issues with comparison, since it has public variables that are stored in an array.

            $diff->ignoreFields(['searchService']);
            $diffed   = $diff->diffedData();
            $diffText = '';

            $changedFields = [];
            foreach ($diffed->toMap() as $field => $prop) {
                if (is_object($prop)) {
                    continue;
                }

                if (is_array($prop)) {
                    $prop = json_encode($prop);
                }

                $changedFields[] = $readOnly        = \SilverStripe\Forms\ReadonlyField::create(
                    'ChangedField' . $field,
                    $field,
                    $prop
                );
                $readOnly->addExtraClass('datachange-field');
            }

            $fields->insertBefore(
                'RawData',
                ToggleCompositeField::create('FieldChanges', 'Changed Fields', $changedFields)
                    ->setStartClosed(false)
                    ->addExtraClass('datachange-field')
            );
        }
        foreach ($fields->dataFields() as $field) {
            $value = $field->getValue();
            if ($value && is_object($value) && (method_exists($value, 'hasMethod') && !$value->hasMethod('forTemplate') || !method_exists(
                $value,
                'forTemplate'
            ))) {
                $field->setValue('[Missing ' . $value::class . '::forTemplate]');
            }
        }

        return $fields->makeReadonly();
    }

    /**
     * Track a change to a DataObject
     * */
    public function track(DataObject $changedObject, $type = 'Change'): ?self
    {
        $changes = $changedObject->getChangedFields(true, 2);
        if (count($changes)) {
            // remove any changes to ignored fields
            $ignored = $changedObject->hasMethod('getIgnoredFields') ? $changedObject->getIgnoredFields() : null;
            if ($ignored) {
                $changes = array_diff_key($changes, $ignored);
                foreach ($ignored as $ignore) {
                    if (isset($changes[$ignore])) {
                        unset($changes[$ignore]);
                    }
                }
            }
        }

        $fieldBlacklist = self::config()->get('field_blacklist');
        if(is_array($fieldBlacklist)) {
            foreach ($fieldBlacklist as $key) {
                if (isset($changes[$key])) {
                    unset($changes[$key]);
                }
            }
        }

        if ((empty($changes) && $type == 'Change')) {
            return null;
        }

        if ($type === 'Delete' && Versioned::get_reading_mode() === 'Stage.Live') {
            $type = 'Delete from Live';
        }

        $this->ChangeType = $type;

        $this->ChangeRecordClass = $changedObject->ClassName;
        $this->ChangeRecordID    = $changedObject->ID;
        // @TODO this will cause issue for objects without titles
        $this->ObjectTitle       = $changedObject->Title;
        $this->Stage             = Versioned::get_reading_mode();

        $before = [];
        $after  = [];

        if ($type != 'Change' && $type != 'New') { // If we are (un)publishing we want to store the entire object
            $before = ($type === 'Unpublish') ? $changedObject->toMap() : null;
            $after  = ($type === 'Publish') ? $changedObject->toMap() : null;
        } else { // Else we're tracking the changes to the object
            foreach ($changes as $field => $change) {
                if ($field == 'SecurityID') {
                    continue;
                }

                $before[$field] = $change['before'];
                $after[$field]  = $change['after'];
            }
        }

        if ($this->Before && $this->Before !== 'null' && is_array($before)) {
            //merge the old array last to keep it's value as we want keep the earliest version of each field
            $this->Before = json_encode(array_replace(json_decode($this->Before, true), $before));
        } else {
            $this->Before = json_encode($before);
        }

        if ($this->After && $this->After !== 'null' && is_array($after)) {
            //merge the new array last to keep it's value as we want the newest version of each field
            $this->After = json_encode(array_replace($after, json_decode($this->After, true)));
        } else {
            $this->After = json_encode($after);
        }

        if (self::config()->get('save_request_vars')) {
            $requestVarsBlacklist = self::config()->get('request_vars_blacklist');
            if(is_array($requestVarsBlacklist)) {
                foreach ($requestVarsBlacklist as $key) {
                    unset($_GET[$key]);
                    unset($_POST[$key]);
                }
            }

            $this->GetVars  = json_encode($_GET);
            $this->PostVars = json_encode($_POST);
        }

        if ($member = Security::getCurrentUser()) {
            $this->ChangedByID = $member->ID;
            $this->CurrentEmail = $member->Email;
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $protocol = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https://' : 'http://';
            $port = $_SERVER['SERVER_PORT'] ?? '80';

            $this->CurrentURL = $protocol . $_SERVER["SERVER_NAME"] . ":" . $port . $_SERVER["REQUEST_URI"];
        } elseif (Director::is_cli()) {
            $this->CurrentURL = 'CLI';
        } else {
            $this->CurrentURL = 'Could not determine current URL';
        }

        $this->RemoteIP = $_SERVER['REMOTE_ADDR'] ?? (Director::is_cli() ? 'CLI' : 'Unknown remote addr');
        $this->Referer  = $_SERVER['HTTP_REFERER'] ?? '';
        $this->Agent    = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $this->write();
        return $this;
    }

    /**
     * @return boolean
     * */
    #[\Override]
    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * @return string
     * */
    #[\Override]
    public function getTitle()
    {
        return $this->ChangeRecordClass . ' #' . $this->ChangeRecordID;
    }

    /**
     * Return a description/summary of the user
     * */
    public function getMemberDetails(): string
    {
        if ($user = $this->ChangedBy()) {
            $name = $user->getTitle();
            if ($user->Email) {
                $name .= " <$user->Email>";
            }

            return $name;
        } else {
            return "";
        }
    }

    private function prepareForDataDifferencer($jsonData)
    {
        // NOTE(Jake): 2018-06-21
        //
        // Data Differencer cannot handle arrays within an array,
        //
        // So JSON data that comes from MultiValueField / Text DB fields
        // causes errors to be thrown.
        //
        // So solve this, we simply only decode to a depth of 1. (rather than the 512 default)
        //
        $resultJsonData = json_decode((string) $jsonData, true, 1);
        return $resultJsonData;
    }

    /**
     * Helper method to prune older records
     */
    public static function pruneChangesBefore(DBDatetime $olderThan): int
    {
        DB::prepared_query(
            'DELETE FROM "DataChangeRecord" WHERE "Created" < ? ORDER BY "Created" ASC',
            [ $olderThan->Format(DBDatetime::ISO_DATETIME) ]
        );
        return DB::affected_rows();
    }
}
