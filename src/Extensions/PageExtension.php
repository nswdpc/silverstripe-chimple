<?php

namespace NSWDPC\Chimple\Extensions;

use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;

/**
 * @extends \SilverStripe\Core\Extension<(\Page & static)>
 */
class PageExtension extends Extension
{
    /**
     * Returns a form for the configuration marked 'IsGlobal'
     */
    public function ChimpleGlobalSubscribeForm(): ?\NSWDPC\Chimple\Forms\SubscribeForm
    {
        $config = MailchimpConfig::getGlobalConfig();
        if ($config instanceof \NSWDPC\Chimple\Models\MailchimpConfig) {
            return $config->SubscribeForm();
        }

        return null;
    }

    /**
     * Returns a form based on a config code
     * @param string $config_code a MailchimpConfig.Code value (not an audience ID)
     */
    public function ChimpleSubscribeForm($config_code): ?\NSWDPC\Chimple\Forms\SubscribeForm
    {
        $config = MailchimpConfig::getConfig('', '', $config_code);
        if ($config) {
            return $config->SubscribeForm();
        }

        return null;
    }
}
