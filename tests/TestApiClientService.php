<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Services\ApiClientService;
use SilverStripe\Dev\TestOnly;

/**
 * Simple log handling
 */
class TestApiClientService extends ApiClientService implements TestOnly
{

    public static function getClient(string $api_key, string $api_endpoint = null): object
    {
        return new TestMailchimpApiClient($api_key, $api_endpoint);
    }
}
