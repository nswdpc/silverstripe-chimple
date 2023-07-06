<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Forms\SubscribeForm;
use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use NSWDPC\Chimple\Controllers\ChimpleController;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\SSViewer;

/**
 * Functional test to verify form submission
 * @author James
 */
class ChimpleFunctionalTest extends FunctionalTest
{

    /**
     * @inheritdoc
     */
    protected $usesDatabase = true;

    /**
     * @var string
     */
    protected $test_api_key = 'functional_test_key';

    /**
     * @var string
     */
    protected $default_list_id = 'function_default_list_id';

    /**
     * @var string
     */
    protected $test_list_id = 'functional_different_list_id';

    /**
     * @var string
     */
    protected $test_form_code = 'functionalformcode';


    public function setUp() : void {
        parent::setUp();


        // Inject test form
        Injector::inst()->registerService(
            new TestSubscribeForm(), SubscribeForm::class
        );

        // Suppress themes
        SSViewer::set_themes(
            [
            SSViewer::DEFAULT_THEME
            ]
        );


        // Create default configuration
        $site_config = SiteConfig::current_site_config();
        $site_config->MailchimpEnabled = 1;
        $site_config->write();

        Config::inst()->update(MailchimpConfig::class, 'api_key', $this->test_api_key);
        Config::inst()->update(MailchimpConfig::class, 'list_id', $this->default_list_id);

        // Config record
        $record = [
            'Title' => 'Test configuration for functional test',
            'Code' => $this->test_form_code,
            'IsGlobal' => 1,// global form
            'Heading' => 'Heading for functional test',
            'MailchimpListId' => $this->test_list_id,
            'ArchiveLink' => 'https://example.com',
            'UseXHR' => 0
        ];
        $config = MailchimpConfig::create($record);
        $config->write();
    }

    public function testFormSubmission()
    {

        $this->useTestTheme(__DIR__, 'chimpletest', function () {

            // request default route
            $url = "/mc-subscribe/v1/";
            $page = $this->get($url);

            $formId = "TestSubscribeForm_SubscribeForm_{$this->test_form_code}";
            $email = 'functionaltester@example.org';
            $response = $this->submitForm(
                $formId,
                null,
                [
                    'Name' => 'Functional Tester',
                    'Email' => $email
                ]
            );

            $subscriber = MailchimpSubscriber::get()->filter([
                'Email' => $email,
                'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW
            ])->first();

            $this->assertTrue( $subscriber && $subscriber->exists() );

        });

    }
}
