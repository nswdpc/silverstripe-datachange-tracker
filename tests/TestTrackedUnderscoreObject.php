<?php

declare(strict_types=1);

namespace Symbiote\DataChange\Tests;

use SilverStripe\ORM\DataObject;
use Symbiote\DataChange\Extension\ChangeRecordable;
use SilverStripe\Dev\TestOnly;

/**
 *
 *
 * @author marcus
 */
class TestTrackedUnderscoreObject extends DataObject implements TestOnly
{
    private static string $table_name = 'Symbiote_DataChange_Tests_TestTrackedUnderscoreObject';

    private static array $db = [
        'Title'     => 'Varchar',
    ];

    private static array $many_many = [
        'Kids'      => TestTrackedUnderscoreChild::class,
    ];

    private static array $extensions = [
        ChangeRecordable::class,
    ];
}
