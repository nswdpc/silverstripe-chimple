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
    public function  ChimpleGlobalSubscribeForm() {
        $config = MailchimpConfig::getGlobalConfig();
        if ($config) {
            $use_xhr = Config::inst()->get(MailchimpConfig::class, 'use_xhr');
            return $config->SubscribeForm($use_xhr);
        }
        return null;
    }

    /**
     * Returns a form based on a config code
     * @return Form|null
     */
    public function ChimpleSubscribeForm($config_code, $use_xhr = true)
    {
        $config = MailchimpConfig::getConfig('', '', $config_code);
        if($config) {
            return $config->SubscribeForm($use_xhr);
        }
        return null;
    }
}
