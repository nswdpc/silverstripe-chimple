<?php

namespace NSWDPC\Chimple\Extensions;

use NSWDPC\Chimple\Models\MailchimpConfig;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;

class SiteConfigExtension extends DataExtension
{
    private static array $db = [
        'MailchimpEnabled' => 'Boolean'
    ];

    private static array $has_one = [
        'MailchimpConfig' => MailchimpConfig::class // global element for configuration
    ];

    #[\Override]
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

    #[\Override]
    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->getOwner()->MailchimpConfigID && ($config = MailchimpConfig::get()->byId($this->getOwner()->MailchimpConfigID))) {
            $config->IsGlobal = 1;
            $config->write();
        }
    }

}
