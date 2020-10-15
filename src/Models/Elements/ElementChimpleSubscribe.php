<?php

namespace NSWDPC\Chimple\Models\Elements;

use DNADesign\Elemental\Models\BaseElement;
use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Provide a subscription for element for Elemental
 *
 * @author James
 */
class ElementChimpleSubscribe extends BaseElement
{

    private static $table_name = 'ElementChimpleSubscribe';

    private static $singular_name = 'Mailchimp subscribe';
    private static $plural_name = 'Mailchimp subscribe';

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'MailchimpConfig' => MailchimpConfig::class,
    ];

    public function getType()
    {
        return _t(__CLASS__ . '.BlockType', 'Mailchimp Subscribe');
    }

    private static $title = 'Mailchimp subscribe';
    private static $description = 'Provide a mailchimp subscription form';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $site_config = SiteConfig::current_site_config();
        if($site_config && $site_config->MailchimpEnabled == 0) {
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'NotEnabled',
                    '<p class="message warning">'
                    . _t(__CLASS__ . 'NOT_ENABLED', 'Mailchimp is not enable in site configuration')
                    . '</p>'
                )
            );
        }

        $fields->removeByName([
            'MailchimpConfigID'
        ]);

        $fields->addFieldToTab(
            'Root.Main',
            $eventcollections = DropdownField::create(
                'MailchimpConfigID',
                _t(
                    __CLASS__ . '.SELECT_CONFIGURATION',
                    'Select the list configuration to use for this subscription form'
                ),
                MailchimpConfig::get()->sort('Title ASC')->map('ID','TitleWithDetails')
            )->setEmptyString('')
        );

        return $fields;
    }

    /**
     * Provide $SubscribeForm for template
     * @return Form
     */
    public function getSubscribeForm() {
        if($config = $this->MailchimpConfig()) {
            return $config->SubscribeForm();
        }
        return null;
    }
}
