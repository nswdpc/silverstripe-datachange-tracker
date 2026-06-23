<?php

declare(strict_types=1);

namespace Symbiote\DataChange\Admin;

use SilverStripe\Admin\ModelAdmin;
use Symbiote\DataChange\Model\DataChangeRecord;

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataChangeAdmin extends ModelAdmin
{
    private static array $managed_models = [
        DataChangeRecord::class,
    ];

    private static string $url_segment = 'datachanges';

    private static string $menu_title = 'Data Changes';
}
