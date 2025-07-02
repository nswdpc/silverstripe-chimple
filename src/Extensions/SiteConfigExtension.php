<?php

namespace NSWDPC\Chimple\Extensions;

use NSWDPC\Chimple\Models\MailchimpConfig;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Forms\CheckboxField;
use Silverstripe\Forms\FieldList;
use Silverstripe\Forms\DropdownField;

class SiteConfigExtension extends DataExtension
{
    private static $db = [
        'MailchimpEnabled' => 'Boolean'
    ];

    private static $has_one = [
        'MailchimpConfig' => MailchimpConfig::class // global element for configuration
    ];

    public function updateCmsFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Mailchimp',
            [
                CheckboxField::create(
                    'MailchimpEnabled',
                    _t(__CLASS__. '.MAILCHIMP_ENABLED', 'Mailchimp subscriptions enabled')
                ),
                DropdownField::create(
                    'MailchimpConfigID',
                    _t(__CLASS__. '.DEFAULT_MAILCHIMP_CONFIG', 'Default Mailchimp configuration'),
                    MailchimpConfig::get()->map('ID', 'TitleCode')
                )->setEmptyString('')
            ]
        );
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->owner->MailchimpConfigID) {
            if ($config = MailchimpConfig::get()->byId($this->owner->MailchimpConfigID)) {
                $config->IsGlobal = 1;
                $config->write();
            }
        }
    }

}
