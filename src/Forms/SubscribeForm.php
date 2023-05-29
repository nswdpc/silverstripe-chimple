<?php

namespace NSWDPC\Chimple\Forms;

use NSWDPC\Chimple\Services\Logger;
use SilverStripe\Forms\Form;

/**
 * Subscription form subclass of {@link SilverStripe\Forms\Form}
 * Allows overrides of default form behaviour
 */
class SubscribeForm extends Form {

    /**
     * When the subscribe form has XHR submission enabled, it should not trigger
     * a disableCache on the HTTPCacheControlMiddleware
     *
     * Form data is entered and submitted by POST to the backend
     * error handling occurs in the client.
     *
     * @inheritdoc
     */
    protected function canBeCached()
    {
        if($this->getAttribute('data-xhr') == 1) {
            return true;
        } else {
            return parent::canBeCached();
        }
    }

    /**
     * This method can be used to check the cache-able status of the form
     */
    public function checkCanBeCached() : bool {
        return $this->canBeCached();
    }

}
