<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Forms\SubscribeForm;
use SilverStripe\Dev\TestOnly;

/**
 * Test subscribe form handling
 */
class TestSubscribeForm extends SubscribeForm implements TestOnly
{
    /**
     * No need to spam protection on tests
     */
    public function enableSpamProtection(): mixed
    {
        return null;
    }
}
