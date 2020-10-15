<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Controllers\ChimpleController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Unit test to verify configuration handling
 * @author James
 */
class ChimpleConfigTest extends SapphireTest
{

    protected $usesDatabase = true;

    // protected static $tempDB = true;

    public function testConfiguration()
    {

        // Some test values to check saving

        $site_config = SiteConfig::current_site_config();
        $site_config->MailchimpEnabled = 1;
        $site_config->write();

        $config_api_key = "test_only";
        $default_list_id = 'test_default_list';
        $disable_security_token = false;

        Config::inst()->update(MailchimpConfig::class, 'api_key', $config_api_key);

        Config::inst()->update(MailchimpConfig::class, 'list_id', $default_list_id);

        Config::inst()->update(ChimpleController::class, 'disable_security_token', $disable_security_token);

        $record = [
            'Title' => 'Test configuration',
            'Code' => 'Test-manual-code',
            'IsGlobal' => 1,
            'Heading' => 'My Default Config',
            'MailchimpListId' => 'different_list_id',
            'ArchiveLink' => 'https://example.com',
            'UpdateExisting' => 1,
            'SendWelcome' => 1,
            'ReplaceInterests' => 1,
            'DoubleOptIn' => 0 // tests turn off DoubleOptin
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

        $this->assertTrue($retrieved_config->DoubleOptIn == 0, "Double Opt In setting should be false");

        // test configuration form retrieval
        $form = $config->SubscribeForm();

        $this->assertTrue( $form instanceof Form, "SubscribeForm is not an instance of Form");

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

        $this->assertTrue( $static_form instanceof Form, "Static form for code {$code_value} was not returned");

        $static_fields = $static_form->Fields();

        $static_code = $static_fields->dataFieldByName('code');
        $this->assertTrue( $static_code instanceof HiddenField, "'code' field is not present");

        $static_code_value = $static_code->dataValue();
        $this->assertEquals($static_code_value, $config->Code, "Code value from static form is not the same as config value");

    }
}
