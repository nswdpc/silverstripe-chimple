<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Models\MailchimpSubscriber;
use NSWDPC\Chimple\Services\ApiClientService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit test for Batch Subscribe
 * @author James
 */
class ChimpleBatchSubscriberTest extends SapphireTest
{
    use Configurable;

    protected $usesDatabase = true;

    protected string $test_list_id = 'test-list-id';

    protected string $test_api_key = 'test-api-key';

    protected string $test_obfuscation_chr = "•";

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestApiClientService::create(), ApiClientService::class);
        Environment::setEnv('CHIMPLE_API_KEY', $this->test_api_key);
        Environment::setEnv('CHIMPLE_DEFAULT_LIST_ID', $this->test_list_id);
        Config::modify()->set(MailchimpSubscriber::class, 'obfuscation_chr', $this->test_obfuscation_chr);
        Config::modify()->set(MailchimpSubscriber::class, 'remove_subscriber_tags', false);
        TestMailchimpApiClient::setSubscriberExists(false);
        TestMailchimpApiClient::setSubscriber();
    }

    /**
     * Test batch subscribe
     */
    public function testBatchSubscribe(): void
    {

        $client = MailchimpSubscriber::api();

        $this->assertTrue($client instanceof TestMailchimpApiClient, "Tests require the TestMailchimpApiClient to be the API client class");

        $subscribers = [
            [
                'Email' => 'test1@example.com',
                'Name' => 'Text Example1',
                'FailNoticeSent' => 0
            ],
            [
                'Email' => 'test2@example.com',
                'Name' => 'Text Example2',
                'FailNoticeSent' => 0
            ],
            [
                'Email' => 'test3@example.com',
                'Name' => 'Text Example3',
                'FailNoticeSent' => 0
            ],
            [
                'Email' => 'test4@example.com',
                'Name' => 'Text Example4',
                'FailNoticeSent' => 0
            ],
        ];

        foreach ($subscribers as $subscriber) {
            $record = MailchimpSubscriber::create($subscriber);
            $record->write();
            // flag one as subscribed
            if ($subscriber['Email'] === 'test2@example.com') {
                $record->Status = MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS;
                $record->write();
            }
        }

        $result = MailchimpSubscriber::batch_subscribe();
        $this->assertEquals(3, $result[MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS]);
    }

}
