<?php

namespace NSWDPC\Chimple\Extensions;

use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;

/**
 * @property bool $MailchimpEnabled
 * @property int $MailchimpConfigID
 * @method \NSWDPC\Chimple\Models\MailchimpConfig MailchimpConfig()
 * @extends \SilverStripe\Core\Extension<(\SilverStripe\SiteConfig\SiteConfig & static)>
 */
class SiteConfigExtension extends Extension
{
    private static array $db = [
        'MailchimpEnabled' => 'Boolean'
    ];

    private static array $has_one = [
        'MailchimpConfig' => MailchimpConfig::class // global element for configuration
    ];

    public function updateCmsFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Mailchimp',
            [
                CheckboxField::create(
                    'MailchimpEnabled',
                    _t(self::class. '.MAILCHIMP_ENABLED', 'Mailchimp subscriptions enabled')
                ),
                DropdownField::create(
                    'MailchimpConfigID',
                    _t(self::class. '.DEFAULT_MAILCHIMP_CONFIG', 'Default Mailchimp configuration'),
                    MailchimpConfig::get()->map('ID', 'TitleCode')
                )->setEmptyString('')
            ]
        );
    }

    public function onAfterWrite()
    {
        if ($this->getOwner()->MailchimpConfigID && ($config = MailchimpConfig::get()->byId($this->getOwner()->MailchimpConfigID))) {
            $config->IsGlobal = true;
            $config->write();
        }
    }

}
