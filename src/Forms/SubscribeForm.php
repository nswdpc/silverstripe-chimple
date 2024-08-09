<?php

namespace NSWDPC\Chimple\Forms;

use NSWDPC\Chimple\Services\Logger;
use NSWDPC\Chimple\Traits\SubscriptionForm;
use SilverStripe\Forms\Form;

/**
 * Subscription form subclass of {@link SilverStripe\Forms\Form}
 * Allows overrides of default form behaviour
 */
class SubscribeForm extends Form
{
    use SubscriptionForm;

}
