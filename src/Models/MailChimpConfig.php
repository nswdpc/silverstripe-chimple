<?php

namespace NSWDPC\Chimple\Models;

use NSWDPC\Chimple\Controllers\ChimpleController;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use Silverstripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripe\View\ArrayData;
use Symbiote\MultiValueField\Fields\MultiValueTextField;

/**
 * Configure mailchimp subscriptions - this is linked to {@link Site} for the global form
 *
 * @author James
 */
class MailchimpConfig extends DataObject implements TemplateGlobalProvider, PermissionProvider
{

    private static $list_id = "";// default list (audience) ID
    private static $api_key = "";// API key provided by Mailchimp
    private static $double_optin = true;// have a good reason to set to false
    private static $use_xhr = true;//whether to use XHR for submissions

    private static $success_message = "Thank you for subscribing. You will receive an email to confirm your subscription shortly.";
    private static $error_message = "Sorry, we could not subscribe that email address at the current time. Please try again later.";

    private static $table_name = 'ChimpleConfig';

    private static $singular_name = 'Mailchimp Configuration';
    private static $plural_name = 'Mailchimp Configurations';

    private static $title = "Mailchimp Subscriber Form";
    private static $description = "Configuration for a Mailchimp subscribe form";

    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'Code' => 'Varchar(255)',// auto created, used to identify config
        'IsGlobal' => 'Boolean',
        'Heading' => 'Varchar(255)',
        'MailchimpListId' => 'Varchar(255)',//list to subscribe people to
        'ArchiveLink' =>  'Varchar(255)',//link to newsletter archive page for the list
        'UpdateExisting' => 'Boolean',
        'SendWelcome' => 'Boolean',
        'ReplaceInterests' => 'Boolean',
        'DoubleOptIn' => 'Boolean',// whether to double opt-in subscribers for this configuration
        // for storing tags to submit with subscriber
        'Tags' => 'MultiValueField',
        'UseXHR' => 'Boolean',// whether to submit without redirect
        'BeforeFormContent' => 'HTMLText',
        'AfterFormContent' => 'HTMLText'
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'Title' => 'Title',
        'Code' => 'Code',
        'IsGlobal.Nice' => 'Default',
        'Heading' => 'Heading',
        'MailchimpListId' => 'List',
        'DoubleOptIn' => 'Double Opt-in',
        'UseXHR.Nice' => 'Submit w/o redirect'
    ];

    private static $indexes = [
        'MailchimpListId' => true,
        'Code' => ['type' => 'unique']
    ];

    /**
     * Add default values to database
     * @var array
     */
    private static $defaults = [
        'UpdateExisting' => 1,
        'SendWelcome' => 0,
        'ReplaceInterests' => 0,
        'DoubleOptIn' => 1,
        'IsGlobal' => 0,
        'UseXHR' => 1
    ];

    public function TitleCode() {
        return "{$this->Title} ({$this->Code})";
    }

    public static function isEnabled() {
        $site_config = SiteConfig::current_site_config();
        return $site_config->MailchimpEnabled == 1;
    }

    public static function getDefaultMailchimpListId()
    {
        return Config::inst()->get(MailchimpConfig::class, 'list_id');
    }

    public static function getApiKey()
    {
        return Config::inst()->get(MailchimpConfig::class, 'api_key');
    }

    public function TitleWithCode() {
        return $this->Title . " - (code {$this->Code})";
    }

    public function TitleWithDetails() {
        $title = $this->Title;
        $list_id = $this->getMailchimpListId();
        $title .= " (list {$list_id})";
        return $title;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->Code) {
            $this->Code = bin2hex(random_bytes(16));
        }
        $this->Code = Convert::raw2url($this->Code);

        if($this->IsGlobal == 1) {
            // Ensure only this config is marked global
            DB::query(
                "UPDATE `ChimpleConfig` "
                . " SET IsGlobal = 0 "
                . " WHERE IsGlobal = 1 "
                . " AND ID <> '" . Convert::raw2sql($this->ID) . "'"
            );
        }
    }

    /**
     * Return the current global config
     */
    public static function getGlobalConfig() {
        return MailchimpConfig::get()->filter(['IsGlobal' => 1])->first();
    }

    /**
     * If not configured in the database, return the value in yml
     */
    public function getMailchimpListId()
    {
        $list_id = $this->getField('MailchimpListId');
        if (!$list_id) {
            $list_id = self::getDefaultMailchimpListId();
        }
        return $list_id;
    }

    public function HasMailchimpListId()
    {
        return $this->getMailchimpListId() != '';
    }

    public static function getConfig($id = '', $list_id = '', $code = '')
    {
        if ($id) {
            return MailchimpConfig::get()->byId($id);
        }
        if ($list_id) {
            return MailchimpConfig::get()->filter('MailchimpListId', $list_id)->first();
        }
        if ($code) {
            return MailchimpConfig::get()->filter('Code', $code)->first();
        }
        return false;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $api_key = self::getApiKey();
        if (!$api_key) {
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'NoApiKey',
                    '<p class="message error">'
                    . _t(
                        __CLASS__ . '.NO_API_KEY',
                        'Warning: no API key was found in the system configuration - subscriptions cannot occur until this is set.'
                    )
                    . '</p>'
                ),
                'Title'
            );
        }

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'ArchiveLink',
                _t(
                    __CLASS__ . '.ARCHIVE_URL',
                    'Newsletter archive URL'
                )
            )
        );

        $default_list_id = self::getDefaultMailchimpListId();
        $list_id = $this->getField('MailchimpListId');
        $fields->dataFieldByName('MailchimpListId')
            ->setDescription(
                !$list_id ?
                sprintf(
                    _t(
                        __CLASS__ . '.NO_LIST_ID',
                        "No list Id is set, the default list id '%s' is being used."
                    ),
                    $default_list_id
                ) : ""
            );

        // this is set from SiteConfig
        if($this->IsGlobal == 1) {
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'IsGlobalBanner',
                    '<p class="message info">'
                    . _t(
                        __CLASS__. '.CONFIG_IS_GLOBAL',
                        'This configuration is the default for this website'
                        )
                    . '</p>'
                ),
                'Title'
            );
        }

        $fields->addFieldToTab(
            'Root.Main',
            MultiValueTextField::create(
                'Tags',
                _t(
                    __CLASS__ . '.TAGS_FOR_SUBSCRIPTIONS',
                    'Tags assigned to subscribers'
                )
            )
        );

        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create(
                'UseXHR',
                _t(
                    __CLASS__ . '.USE_XHR',
                    'Submit without redirecting'
                )
            ),
            'Code'
        );

        $fields->addFieldsToTab(
            'Root.Main', [
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
            )->setRows(6)
        ]);

        if($heading = $fields->dataFieldByName('Heading')) {
            $heading->setDescription(_t(
                __CLASS__ . '.HEADING_DESCRIPTON',
                'Displayed above the form'
            ));
        }

        $fields->removeByName('IsGlobal');
        return $fields;
    }

    /**
     * Return signup link
     * @return string
     */
    public function MailchimpLink()
    {
        return singleton(ChimpleController::class)->Link();
    }

    /**
     * Ensure the subscription for the global footer is added
     */
    public function requireDefaultRecords()
    {
        $config = MailchimpConfig::get()->filter(['IsGlobal' => 1])->first();
        if (empty($config->ID)) {
            $config = MailchimpConfig::create([
                'Title' => _t(__CLASS__ . '.DEFAULT_CONFIG_TITLE', 'Default Configuration'),
                'Heading' => _t(__CLASS__ . '.DEFAULT_CONFIG_HEADER', 'Subscribe'),
                'IsGlobal' => 1,
                'MailchimpListId' => null
            ]);
            $config_id = $config->write();
            DB::alteration_message("Created default Mailchimp config record #{$config_id}", "changed");
        } else {
            $config_id = $config->ID;
        }
        if ($config_id) {
            $site_config = SiteConfig::current_site_config();
            if (!empty($site_config->ID) && empty($site_config->MailchimpConfigID)) {
                $site_config->MailchimpConfigID = $config_id;
                $site_config->write();
                DB::alteration_message("Assigned default Mailchimp config record #{$config_id} to site config", "changed");
            }
        }
    }

    /**
     * Use the form provided by the controller
     * @param bool $force_use_xhr whether to submit in place via XHR or not, the default is to let the config decide
     * @return Form
     */
    public function SubscribeForm($force_use_xhr = null)
    {
        // No form available if not enabled
        $enabled = self::isEnabled();
        if(!$enabled) {
            return null;
        }
        $form = Injector::inst()->create(ChimpleController::class)->SubscribeForm();
        // to return a form, there must be one and the Code must exist
        if($form && $this->Code) {
            $form->setHTMLID( Convert::raw2htmlid( $form->FormName() . " " . $this->Code) );
            // apply the code for this config to the form
            $code_field = HiddenField::create('code', 'code', $this->Code);
            $code_field->setForm($form);
            $form->Fields()->push($code_field);
            if ($this->Heading) {
                $form->setLegend($this->Heading);
            }
            $form->addExtraClass('form-subscribe');

            // handle use of XHR submission
            $use_xhr = $this->UseXHR;// use the default
            if(!is_null($force_use_xhr)) {
                $use_xhr = $force_use_xhr;
            }
            if($use_xhr) {
                $form->setAttribute('data-xhr',1);
            }
            return $form;
        }

        return null;
    }

    /**
     * Return alerts for the form
     * @return string
     */
    public function Alerts()
    {
        return '<div class="hidden alert alert-success" data-type="success">'
        . _t(__CLASS__ . '.SUBSCRIBE_SUCCESS', htmlspecialchars($this->config()->get('success_message')))
        . '</div>'
        . '<div class="hidden alert alert-danger" data-type="error">'
        . _t(__CLASS__ . '.SUBSCRIBE_ERROR', htmlspecialchars($this->config()->get('error_message')))
        . '</div>'
        . '<div class="hidden alert alert-info" data-type="info"></div>';// info added by JS
    }

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_CONFIG_VIEW');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'MAILCHIMP_CONFIG_CREATE');
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_CONFIG_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_CONFIG_DELETE');
    }

    public function providePermissions()
    {
        return [
            'MAILCHIMP_CONFIG_VIEW' => [
                'name' => 'View Mailchimp configuration',
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_CONFIG_EDIT' => [
                'name' => 'Edit Mailchimp configuration',
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_CONFIG_CREATE' => [
                'name' => 'Create Mailchimp configuration',
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_CONFIG_DELETE' => [
                'name' => 'Delete Mailchimp configuration',
                'category' => 'Mailchimp',
            ]
        ];
    }

    /**
     * Render this record using a template
     * @return DBHTMLText|null
     */
    public function forTemplate($force_use_xhr = null)
    {
        $form = $this->SubscribeForm($force_use_xhr);
        if($form) {
            return $this->customise(['Form'=>$form])->renderWith( self::class );
        }
        return null;
    }

    /**
     * Get a subscribe form based on a config code
     * This first parameter is the list code (not the MC audience ID)
     * The 2nd parameter is a 1 or 0 representing whether to handle the submission via XHR
     * This is called from a template calling $ChimpleSubscribeForm('code'[,0|1])
     * @param array
     * @return string|null
     */
    public static function get_chimple_subscribe_form(...$args)
    {
        $code = isset($args[0]) ? $args[0] : '';
        if ($code) {
            $config = self::getConfig('', '', $code);
            if ($config) {
                // default to let the config decide
                $force_use_xhr = null;
                if(isset($args[1])) {
                    if($args[1] === '0') {
                        // string '0' passed in as an arg in the template
                        $force_use_xhr = false;
                    } else if($args[1] === '1') {
                        // string '1' passed in as an arg in the template
                        $force_use_xhr = true;
                    }
                }
                return $config->forTemplate($force_use_xhr);
            }
        }
        return null;
    }

    /**
     * Get the subscribe form for the current global config
     * This is called from a template calling $ChimpleSubscribeForm('code')
     * @return string|null
     */
    public static function get_chimple_global_subscribe_form()
    {
        $config = self::getGlobalConfig();
        if ($config) {
            $use_xhr = static::config()->get('use_xhr');
            return $config->forTemplate($use_xhr);
        }
        return null;
    }

    public static function get_template_global_variables()
    {
        return [
            'ChimpleSubscribeForm' => 'get_chimple_subscribe_form',
            'ChimpleGlobalSubscribeForm' => 'get_chimple_global_subscribe_form'
        ];
    }
}
