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
    private static string $table_name = 'ElementChimpleSubscribe';

    private static string $singular_name = 'Mailchimp subscribe';

    private static string $plural_name = 'Mailchimp subscribe';

    private static string $icon = 'font-icon-up-circled';

    private static array $db = [
        'UseXHR' => 'Boolean',// whether to submit without redirect
        'BeforeFormContent' => 'HTMLText',
        'AfterFormContent' => 'HTMLText',
        'ImageAlignment' => 'Varchar(32)',
    ];

    private static array $defaults = [
        'UseXHR' => 1
    ];

    /**
     * Has_one relationship
     */
    private static array $has_one = [
        'MailchimpConfig' => MailchimpConfig::class,
        'Image' => Image::class
    ];

    private static array $owns = [
        'Image'
    ];

    #[\Override]
    public function getType()
    {
        return _t(self::class . '.BlockType', 'Mailchimp Subscribe');
    }

    private static string $title = 'Mailchimp subscribe';

    private static string $description = 'Provide a mailchimp subscription form';

    #[\Override]
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
                    . _t(self::class . 'NOT_ENABLED', 'Mailchimp is not enable in site configuration')
                    . '</p>'
                )
            );
        }

        $fields->removeByName([
            'MailchimpConfigID'
        ]);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                DropdownField::create(
                    'MailchimpConfigID',
                    _t(
                        self::class . '.SELECT_CONFIGURATION',
                        'Select the list configuration to use for this subscription form'
                    ),
                    MailchimpConfig::get()->sort('Title ASC')->map('ID', 'TitleWithDetails')
                )->setEmptyString(''),
                CheckboxField::create(
                    'UseXHR',
                    _t(
                        self::class . '.USE_XHR',
                        'Submit without redirecting'
                    ),
                ),
                HTMLEditorField::create(
                    'BeforeFormContent',
                    _t(
                        self::class . '.BEFORE_CONTENT',
                        'Content to show before form'
                    )
                )->setRows(6),
                HTMLEditorField::create(
                    'AfterFormContent',
                    _t(
                        self::class . '.AFTER_CONTENT',
                        'Content to show after form'
                    )
                )->setRows(6),
                UploadField::create(
                    'Image',
                    _t(self::class . '.IMAGE', 'Image')
                )->setTitle(
                    _t(
                        self::class . '.ADD_IMAGE_TO_CONTENT_BLOCK',
                        'Add an image'
                    )
                )->setFolderName('blocks/content/' . $this->owner->ID)
                ->setAllowedMaxFileNumber(1)
                ->setIsMultiUpload(false),
                DropdownField::create(
                    'ImageAlignment',
                    _t(self::class . '.IMAGE_ALIGNMENT', 'Image alignment'),
                    [
                        'left' => _t(self::class . '.LEFT', 'Left'),
                        'right' => _t(self::class . '.RIGHT', 'Right')
                    ]
                )->setEmptyString('Choose an option')
                ->setDescription(
                    _t(self::class . '.IMAGE_ALIGNMENT_DESCRIPTION', 'Use of this option is dependent on the theme')
                )
            ]
        );

        return $fields;
    }

    /**
     * Provide $SubscribeForm for template
     * When called in the context of the administration area, return null
     */
    public function getSubscribeForm(): ?SubscribeForm
    {

        if(Controller::curr() instanceof LeftAndMain) {
            return null;
        }

        if($config = $this->MailchimpConfig()) {
            // render the form with this element's XHR setting overriding the config being used
            $form = $config->SubscribeForm($this->UseXHR == 1);
            return $form;
        }

        return null;
    }
}
