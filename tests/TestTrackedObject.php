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
class TestTrackedObject extends DataObject implements TestOnly
{
    private static string $table_name = 'TestTrackedObject';

    private static array $db = [
        'Title'     => 'Varchar',
    ];

    private static array $many_many = [
        'Kids'      => TestTrackedChild::class,
    ];

    private static array $extensions = [
        ChangeRecordable::class,
    ];
}
