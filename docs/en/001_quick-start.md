## Usage

Add the ChangeRecordable extension to the dataobjects you wish to track

```yml
App\MyDataObject:
  extensions:
    - Symbiote\DataChange\Extension\ChangeRecordable
```

If you are applying the extension to the SiteTree (silverstripe/cms), use the SiteTreeChangeRecordable
extension to record publish/unpublish actions.

To track changes to many\_many relationships, you must use a custom
ManyManyRelationship class, as well as indicate which relationships need
tracking. This can be directly configured via the Injector

For example

```yml
SilverStripe\Core\Injector\Injector:
  SilverStripe\ORM\ManyManyList:
    class: Symbiote\DataChange\Model\TrackedManyManyList
    properties:
      trackedRelationships:
        - Page_Regions
```

will track the "Regions" relationship defined on the Page class;

```php
<?php
private static array $many_many = [
	'Regions'	=> 'SomeObject',
];
```

## Capturing URL parameters

Set the `save_request_vars` option to 1, and GET and POST vars will be recorded too.

```yml
Symbiote\DataChange\Model\DataChangeRecord:
  save_request_vars: 1
```

## Ignoring fields

In some cases it may not be desirable to track changes to all fields of an object. You can define ignored fields in your yml config like so:

```yml
Symbiote\DataChange\Extension\ChangeRecordable:
  ignored_fields:
    NameOfObjectClass:
      - NameOfField
```

Or, for the same field name across all objects

```yml
Symbiote\DataChange\Model\DataChangeRecord:
  field_blacklist:
    - Password
    - Email

```

Also, you may wish to blacklist some request variables from being stored

```yml
Symbiote\DataChange\Model\DataChangeRecord:
  request_vars_blacklist:
    - url
    - SecurityID
```

## Significant Change tracking

Sometimes reporting changes to certain fields to CMS users is desirable (e.g seeing the last time a field was updated).
This is handled by `SignificantChangeRecordable`, which looks for a list of `significant_fields`.

Example:

```yml
App\TeamMember:
  significant_fields:
    - 'Name'
    - 'Address'
    - 'OfficeNumber'
    - 'Position'
    - 'Mobile'
  extensions:
    - 'Symbiote\DataChange\Extension\ChangeRecordable'
    - 'Symbiote\DataChange\Extension\SignificantChangeRecordable'
```

## Pruning old data

Over time, the data recorded will become overwhelming in size. This may not be a problem for you, but if it is you can regularly prune it to retain just (N) months of data at a time.

Simply create the `PruneChangesBeforeJob` from the QueuedJob admin section of the CMS, using a constructor param of something like "-6 months".

The job will restart itself by default every 24 hours.
