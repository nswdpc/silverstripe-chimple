<?php

namespace NSWDPC\Chimple\Models\Elements;

use DNADesign\Elemental\Models\BaseElement;
use NSWDPC\Chimple\Forms\SubscribeForm;
use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Provide a subscription form element for Elemental
 * Content editors can choose a list and whether to subscribe in place via AJAX
 *
 * @author James
 */
class ElementChimpleSubscribe extends BaseElement
{

    private static $table_name = 'ElementChimpleSubscribe';

    private static $singular_name = 'Mailchimp subscribe';
    private static $plural_name = 'Mailchimp subscribe';

    private static $icon = 'font-icon-up-circled';

    private static $db = [
        'UseXHR' => 'Boolean',// whether to submit without redirect
        'BeforeFormContent' => 'HTMLText',
        'AfterFormContent' => 'HTMLText',
        'ImageAlignment' => 'Varchar(32)',
    ];

    private static $defaults = [
        'UseXHR' => 1
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'MailchimpConfig' => MailchimpConfig::class,
        'Image' => Image::class
    ];

    private static $owns = [
        'Image'
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

        $fields->addFieldsToTab(
            'Root.Main', [
                DropdownField::create(
                    'MailchimpConfigID',
                    _t(
                        __CLASS__ . '.SELECT_CONFIGURATION',
                        'Select the list configuration to use for this subscription form'
                    ),
                    MailchimpConfig::get()->sort('Title ASC')->map('ID','TitleWithDetails')
                )->setEmptyString(''),
                CheckboxField::create(
                    'UseXHR',
                    _t(
                        __CLASS__ . '.USE_XHR',
                        'Submit without redirecting'
                    ),
                ),
                HTMLEditorField::create(
                    'BeforeFormContent',
                    _t(
                        __CLASS__ . '.BEFORE_CONTENT',
                        'Content to show before form'
                    )
                )->setRows(6),
                HTMLEditorField::create(
                    'AfterFormContent',
                    _t(
                        __CLASS__ . '.AFTER_CONTENT',
                        'Content to show after form'
                    )
                )->setRows(6),
                UploadField::create(
                    'Image',
                    _t(__CLASS__ . '.IMAGE', 'Image')
                )->setTitle(
                    _t(
                        __CLASS__ . '.ADD_IMAGE_TO_CONTENT_BLOCK',
                        'Add an image'
                    )
                )->setFolderName('blocks/content/' . $this->owner->ID)
                ->setAllowedMaxFileNumber(1)
                ->setIsMultiUpload(false),
                DropdownField::create(
                    'ImageAlignment',
                    _t(__CLASS__ . '.IMAGE_ALIGNMENT', 'Image alignment'),
                    [
                        'left' => _t(__CLASS__ . '.LEFT', 'Left'),
                        'right' => _t(__CLASS__ . '.RIGHT', 'Right')
                    ]
                )->setEmptyString('Choose an option')
                ->setDescription(
                    _t(__CLASS__ . '.IMAGE_ALIGNMENT_DESCRIPTION', 'Use of this option is dependent on the theme')
                )
            ]
        );

        return $fields;
    }

    /**
     * Provide $SubscribeForm for template
     * When called in the context of the administration area, return null
     */
    public function getSubscribeForm(): ?SubscribeForm {

        if(Controller::curr() instanceof LeftAndMain) {
            return null;
        }

        if($config = $this->MailchimpConfig()) {
            // render the form with this element's XHR setting overriding the config being used
            $form = $config->SubscribeForm( $this->UseXHR == 1 );
            return $form;
        }
        return null;
    }
}
