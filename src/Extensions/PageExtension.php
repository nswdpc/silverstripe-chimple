<?php

namespace NSWDPC\Chimple\Extensions;

use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;

class PageExtension extends Extension
{
    /**
     * Returns a form for the configuration marked 'IsGlobal'
     * @return Form|null
     */
    public function ChimpleGlobalSubscribeForm()
    {
        $config = MailchimpConfig::getGlobalConfig();
        if ($config) {
            return $config->SubscribeForm();
        }
        return null;
    }

    /**
     * Returns a form based on a config code
     * @return Form|null
     * @param string $config_code a MailchimpConfig.Code value (not an audience ID)
     */
    public function ChimpleSubscribeForm($config_code)
    {
        $config = MailchimpConfig::getConfig('', '', $config_code);
        if($config) {
            return $config->SubscribeForm();
        }
        return null;
    }
}
