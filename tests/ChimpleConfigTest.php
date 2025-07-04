<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Forms\XhrSubscribeForm;
use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Unit test to verify configuration handling
 * @author James
 */
class ChimpleConfigTest extends SapphireTest
{
    /**
     * @inheritdoc
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected $test_api_key = 'test_only';

    /**
     * @var string
     */
    protected $default_list_id = 'test_default_list';

    /**
     * @var string
     */
    protected $test_list_id = 'different_list_id';

    /**
     * @inheritdoc
     */
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        // Create default configuration
        $site_config = SiteConfig::current_site_config();
        $site_config->MailchimpEnabled = 1;
        $site_config->write();

        Config::modify()->set(MailchimpConfig::class, 'api_key', $this->test_api_key);
        Config::modify()->set(MailchimpConfig::class, 'list_id', $this->default_list_id);

        // Config record
        $record = [
            'Title' => 'Test configuration',
            'Code' => 'Test-manual-code',
            'IsGlobal' => 1,
            'Heading' => 'My Default Config',
            'MailchimpListId' => $this->test_list_id,
            'ArchiveLink' => 'https://example.com',
            'UseXHR' => 0
        ];
        $config = MailchimpConfig::create($record);
        $config->write();
    }

    protected function getMailchimpConfig()
    {

        // get config for the test list
        $config = MailchimpConfig::get()->filter(['MailchimpListId' => $this->test_list_id])->first();
        $this->assertTrue($config && $config->exists(), "Configuration does not exist in DB");

        // Api key check
        $api_key = $config->getApiKey();
        $this->assertEquals($api_key, $this->test_api_key, "API key equals");

        // list check
        $list_id = $config->getMailchimpListId();
        $this->assertNotEquals($list_id, $this->default_list_id, "List Id should not be the same as default list id");
        $this->assertTrue($config->HasMailchimpListId(), "Config should have a list id");

        // test config retrieval via Code
        $retrieved_config = MailchimpConfig::getConfig('', '', $config->Code);
        $this->assertEquals($retrieved_config->ID, $config->ID, "Configs should be the same");

        return $retrieved_config;

    }

    public function testConfiguration(): void
    {

        $forceXhr = true;

        Config::modify()->set(XhrSubscribeForm::class, 'disable_security_token', false);
        $config = $this->getMailchimpConfig();

        // test configuration form retrieval
        $form = $config->SubscribeForm($forceXhr);

        $this->assertTrue($form instanceof Form, "SubscribeForm is not an instance of Form");

        $this->assertEquals(1, $form->getAttribute('data-xhr'), "Form should have XHR attribute enabled");

        $fields = $form->Fields();

        $email = $fields->dataFieldByName('Email');
        $this->assertTrue($email instanceof EmailField, "Email field is not an email field");

        $name = $fields->dataFieldByName('Name');
        $this->assertTrue($name instanceof TextField, "Name field is not an text field");

        $token_name = SecurityToken::get_default_name();
        $token = $fields->dataFieldByName($token_name);
        $this->assertTrue($token instanceof HiddenField, "{$token_name} field is not present");


        $code_field = $fields->dataFieldByName('code');
        $this->assertTrue($code_field instanceof HiddenField, "'code' field is not present");

        $code_value = $code_field->dataValue();
        $this->assertEquals($code_value, $config->Code, "Code value in form is not the same as config value");

        $static_form = MailchimpConfig::get_chimple_subscribe_form($code_value);

        $this->assertTrue($static_form instanceof DBHTMLText, "Static form for code {$code_value} was not returned");

        $needle = " value=\"{$code_value}\" ";
        $this->assertTrue(str_contains($static_form->forTemplate(), $needle), "Missing {$code_value} input from form HTML");

    }


    public function testCanBeCached(): void
    {

        Config::modify()->set(XhrSubscribeForm::class, 'disable_security_token', true);

        $config = $this->getMailchimpConfig();

        // test forcing XHR
        $forceXhr = true;
        $form = $config->SubscribeForm($forceXhr);
        $this->assertTrue($form->checkCanBeCached());

        // test not forcing XHR
        $forceXhr = false;
        $form = $config->SubscribeForm($forceXhr);
        $this->assertFalse($form->checkCanBeCached());

        // test using config value
        // config is turned off
        $config->UseXHR = 0;
        $form = $config->SubscribeForm();// default null value
        $this->assertFalse($form->checkCanBeCached());
        // config turned on
        $config->UseXHR = 1;
        $form = $config->SubscribeForm();// default null value
        $this->assertTrue($form->checkCanBeCached());

    }

    public function testSubscribeFormTemplateVariable(): void
    {
        $config = $this->getMailchimpConfig();
        $config->UseXHR = 0;
        $config->write();

        // Use config value
        $template = MailchimpConfig::get_chimple_subscribe_form($config->Code, null);
        $this->assertTrue(in_array(str_contains($template, 'data-xhr="1"'), [0, false], true), "Attribute is not in template");
        $config->UseXHR = 1;
        $config->write();
        $template = MailchimpConfig::get_chimple_subscribe_form($config->Code, null);
        $this->assertTrue(str_contains($template, 'data-xhr="1"'), "Attribute is in template");

        // template override
        $config->UseXHR = 0;
        $config->write();
        $template = MailchimpConfig::get_chimple_subscribe_form($config->Code, '1');
        $this->assertTrue(str_contains($template, 'data-xhr="1"'), "Attribute is in template");
        $config->UseXHR = 0;
        $config->write();
        $template = MailchimpConfig::get_chimple_subscribe_form($config->Code, '0');
        $this->assertTrue(in_array(str_contains($template, 'data-xhr="1"'), [0, false], true), "Attribute is not in template");
    }

    public function testGlobalSubscribeFormTemplateVariable(): void
    {
        $config = $this->getMailchimpConfig();
        $config->UseXHR = 0;
        $config->write();
        // Use config value
        $template = MailchimpConfig::get_chimple_global_subscribe_form();
        $this->assertTrue(in_array(str_contains($template, 'data-xhr="1"'), [0, false], true), "Attribute is not in template");
        $config->UseXHR = 1;
        $config->write();
        $template = MailchimpConfig::get_chimple_global_subscribe_form();
        $this->assertTrue(str_contains($template, 'data-xhr="1"'), "Attribute is in template");
    }
}
