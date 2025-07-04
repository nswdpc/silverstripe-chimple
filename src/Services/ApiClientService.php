<?php

namespace NSWDPC\Chimple\Services;

use DrewM\MailChimp\MailChimp;
use SilverStripe\Core\Injector\Injectable;

/**
 * Simple log handling
 */
class ApiClientService
{
    use Injectable;

    public static function getClient(string $api_key, string $api_endpoint = null): object
    {
        return new MailChimp($api_key, $api_endpoint);
    }
}
