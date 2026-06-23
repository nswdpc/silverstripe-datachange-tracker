<?php

declare(strict_types=1);

namespace Symbiote\DataChange\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

/**
 *
 *
 * @author marcus
 */
class TestTrackedChild extends DataObject implements TestOnly
{
    private static string $table_name = 'TestTrackedChild';

    private static array $db = [
        'Title'     => 'Varchar',
    ];
}
