<?php

namespace NSWDPC\Chimple\Extensions;

use SilverStripe\Core\Extension;

/**
 * This extension can be applied at the project level in situations where
 * you want the form SecurityToken turned off.
 *
 * You should have an alternate method of form protection if this occurs.
 */
class DisableSecurityTokenExtension extends Extension
{
    public function updateChimpleSubscribeForm(): void {
        $this->getOwner()->disableSecurityToken();
    }
}
