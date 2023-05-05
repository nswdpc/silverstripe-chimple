<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Forms\SubscribeForm;
use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Controllers\ChimpleController;
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

    protected $usesDatabase = true;


    protected function getMailchimpConfig() {
        // Some test values to check saving

        $site_config = SiteConfig::current_site_config();
        $site_config->MailchimpEnabled = 1;
        $site_config->write();

        $config_api_key = "test_only";
        $default_list_id = 'test_default_list';

        Config::inst()->update(MailchimpConfig::class, 'api_key', $config_api_key);

        Config::inst()->update(MailchimpConfig::class, 'list_id', $default_list_id);

        $record = [
            'Title' => 'Test configuration',
            'Code' => 'Test-manual-code',
            'IsGlobal' => 1,
            'Heading' => 'My Default Config',
            'MailchimpListId' => 'different_list_id',
            'ArchiveLink' => 'https://example.com',
            'UseXHR' => 0
        ];

        $config = MailchimpConfig::create($record);
        $config->write();

        $this->assertTrue($config->exists(), "Configuration does not exist in DB");

        $api_key = $config->getApiKey();

        $list_id = $config->getMailchimpListId();

        $this->assertEquals($api_key, $config_api_key, "API key is not equal");

        $this->assertNotEquals($list_id, $default_list_id, "List Id should not be the same as default list id");

        $this->assertTrue( $config->HasMailchimpListId(), "Config should have a list id");

        // test config retrieval
        $retrieved_config = MailchimpConfig::getConfig('','', $config->Code);

        $this->assertEquals($retrieved_config->ID, $config->ID, "Configs should be the same");

        return $retrieved_config;

    }

    public function testConfiguration()
    {

        $forceXhr = true;
        $config = $this->getMailchimpConfig();

        // test configuration form retrieval
        $form = $config->SubscribeForm($forceXhr);

        $this->assertTrue( $form instanceof Form, "SubscribeForm is not an instance of Form");

        $this->assertEquals( 1, $form->getAttribute('data-xhr'), "Form should have XHR attribute enabled" );

        $fields = $form->Fields();

        $email = $fields->dataFieldByName('Email');
        $this->assertTrue( $email instanceof EmailField, "Email field is not an email field");

        $name = $fields->dataFieldByName('Name');
        $this->assertTrue( $email instanceof TextField, "Name field is not an text field");

        $token_name = SecurityToken::get_default_name();
        $token = $fields->dataFieldByName($token_name);
        $this->assertTrue( $token instanceof HiddenField, "{$token_name} field is not present");


        $code_field = $fields->dataFieldByName('code');
        $this->assertTrue( $code_field instanceof HiddenField, "'code' field is not present");

        $code_value = $code_field->dataValue();
        $this->assertEquals($code_value, $config->Code, "Code value in form is not the same as config value");

        $static_form = MailchimpConfig::get_chimple_subscribe_form($code_value);

        $this->assertTrue( $static_form instanceof DBHTMLText, "Static form for code {$code_value} was not returned");

        $needle = " value=\"{$code_value}\" ";
        $this->assertTrue( strpos($static_form->forTemplate(), $needle) !== false, "Missing {$code_value} input from form HTML");

    }


    public function testCanBeCached() {

        $config = $this->getMailchimpConfig();

        // test forcing XHR
        $forceXhr = true;
        $form = $config->SubscribeForm($forceXhr);
        $this->assertTrue( $form->checkCanBeCached() );

        // test not forcing XHR
        $forceXhr = false;
        $form = $config->SubscribeForm($forceXhr);
        $this->assertFalse( $form->checkCanBeCached() );

        // test using config value
        // config is turned off
        $config->UseXHR = 0;
        $form = $config->SubscribeForm();// default null value
        $this->assertFalse( $form->checkCanBeCached() );
        // config turned on
        $config->UseXHR = 1;
        $form = $config->SubscribeForm();// default null value
        $this->assertTrue( $form->checkCanBeCached() );

    }
}
