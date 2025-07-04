<?php

namespace NSWDPC\Chimple\Traits;

/**
 * Trait for use by subscription forms
 */
trait SubscriptionForm
{
    /**
     * This method can be used to check the cache-able status of the form
     */
    public function checkCanBeCached(): bool
    {
        return $this->canBeCached();
    }

}
