<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use NSWDPC\Chimple\Services\ApiClientService;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit test to verify subscription handling via the API
 * @author James
 */
class ChimpleSubscriberTest extends SapphireTest
{
    use Configurable;

    protected $usesDatabase = true;

    protected string $test_list_id = 'test-list-id';

    protected string $test_api_key = 'test-api-key';

    protected string $test_fname = 'Test';

    protected string $test_lname = 'Subscriber';

    protected string $test_email = 'test.subscriber@example.com';

    protected array $test_tags = ['TestOne','TestTwo'];

    protected array $test_update_tags = ['TestThree','TestFour'];

    protected string $test_obfuscation_chr = "•";

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Injector::inst()->registerService(TestApiClientService::create(), ApiClientService::class);
        Config::modify()->set(MailchimpConfig::class, 'list_id', $this->test_list_id);
        Config::modify()->set(MailchimpConfig::class, 'api_key', $this->test_api_key);
        Config::modify()->set(MailchimpSubscriber::class, 'obfuscation_chr', $this->test_obfuscation_chr);
        Config::modify()->set(MailchimpSubscriber::class, 'remove_subscriber_tags', false);
        TestMailchimpApiClient::setSubscriberExists(false);
        TestMailchimpApiClient::setSubscriber();
    }

    /**
     * Test a new subscriber who is not already in the list/audience
     */
    public function testNewSubscriber(): void
    {

        $client = MailchimpSubscriber::api();

        $this->assertTrue($client instanceof TestMailchimpApiClient, "Tests require the TestMailchimpApiClient to be the API client class");

        if ($this->test_email === '' || !Email::is_valid_address($this->test_email)) {
            throw new \Exception("The test needs a test email user");
        }

        // set a obfuscation chr
        Config::inst()->get(MailchimpSubscriber::class, 'obfuscation_chr');

        // MailchimpSubscriber record values
        $record = [
            'Name' => "{$this->test_fname} {$this->test_lname}",
            'Email' => $this->test_email,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($this->test_tags)
        ];
        $subscriber = MailchimpSubscriber::create($record);
        $subscriber->write();

        // Store the subscriber for the test api client to provide mock responses
        TestMailchimpApiClient::setSubscriber([
            'fname' => $this->test_fname,
            'lname' => $this->test_fname,
            'email' => $this->test_email,
            'tags' => $this->test_tags,
        ]);

        $this->assertTrue($subscriber->exists(), "Subscriber exists");

        $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_NEW, "Subscriber should be 'new' status");

        $this->assertEquals($subscriber->Name, $this->test_fname, "Subscriber should have fname of {$this->test_fname}");

        $this->assertEquals($subscriber->Surname, $this->test_lname, "Subscriber shold have lname of {$this->test_lname}");

        // test an API subscription
        $this->assertEquals($this->test_list_id, $subscriber->getMailchimpListId(), "List id should be the test list id");

        $subscribe_record = $subscriber->getSubscribeRecord();

        $this->assertIsArray($subscribe_record, "Subscribe record is an array");

        $this->assertNotEmpty($subscribe_record, "Subscribe Record is not empty");

        $this->assertNotEmpty($subscribe_record['merge_fields'] ?? [], "Subscribe record merge_fields is not empty");

        $this->assertNotEmpty($subscribe_record['email_type'] ?? '', "Record email_type is not empty");

        $this->assertEquals($subscriber->Email, $subscribe_record['email_address'], "Subscribed email_address matches");

        // check merge fields
        $sync_fields = MailchimpSubscriber::config()->get('sync_fields');
        $merge_fields = $subscribe_record['merge_fields'];
        foreach ($sync_fields as $field => $tag) {
            $this->assertTrue(isset($merge_fields[ $tag ]) && $merge_fields[ $tag ] = $subscriber->getField($field), "Merge field tag {$tag} value does not match subscriber {$field} value");
        }

        // trigger test API client to handle as new subscriber
        TestMailchimpApiClient::setSubscriberExists(false);

        $result = $subscriber->subscribe();

        $this->assertTrue($result);

        $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS, "Status of subscriber should be subscribed");

        $this->assertEquals($subscriber->SubscribedId, MailchimpSubscriber::getMailchimpSubscribedId($record['Email']), "Email hash should match");

        $this->assertNotEmpty($subscriber->SubscribedWebId);

        $this->assertNotEmpty($subscriber->SubscribedUniqueEmailId);

        $this->assertTrue(substr_count($subscriber->Email, $this->test_obfuscation_chr) > 0, "Email is obfuscated");

        $this->assertTrue(substr_count($subscriber->Name, $this->test_obfuscation_chr) > 0, "Name is obfuscated");

        $this->assertTrue(substr_count($subscriber->Surname, $this->test_obfuscation_chr) > 0, "Surname is obfuscated");

        TestMailchimpApiClient::setSubscriberExists(true);// flip to exist mode fpr test
        $mailchimpRecord = MailchimpSubscriber::checkExistsInList($this->test_list_id, $record['Email']);
        $this->assertIsArray($mailchimpRecord);
        $this->assertTrue(!empty($mailchimpRecord['id']), "The subscriber exists in list {$this->test_list_id}");
        $this->assertEquals(MailchimpSubscriber::MAILCHIMP_SUBSCRIBER_PENDING, $mailchimpRecord['status'], "The subscriber is pending");

        $tagDelta = $subscriber->getTagDelta();

        $this->assertEquals($this->test_tags, $tagDelta);

    }


    /**
     * Test a new subscriber who is already in the list/audience
     */
    public function testExistingSubscriber(): void
    {

        $client = MailchimpSubscriber::api();

        $this->assertTrue($client instanceof TestMailchimpApiClient, "Tests require the TestMailchimpApiClient to be the API client class");

        if ($this->test_email === '' || !Email::is_valid_address($this->test_email)) {
            throw new \Exception("The test needs a test email user");
        }

        // set a obfuscation chr
        Config::inst()->get(MailchimpSubscriber::class, 'obfuscation_chr');

        // MailchimpSubscriber record values
        $record = [
            'Name' => "{$this->test_fname} {$this->test_lname}",
            'Email' => $this->test_email,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($this->test_update_tags)
        ];

        $subscriber = MailchimpSubscriber::create($record);
        $subscriber->write();

        // Store the subscriber for the test api client to provide mock responses
        TestMailchimpApiClient::setSubscriber([
            'fname' => $this->test_fname,
            'lname' => $this->test_fname,
            'email' => $this->test_email,
            'tags' => $this->test_tags,// the ones that already exist
            'tags_for_update' => $this->test_update_tags // updated tags
        ]);

        // Trigger existing user handling
        TestMailchimpApiClient::setSubscriberExists(true);

        $result = $subscriber->subscribe();

        $this->assertTrue($result);

        $currentTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMPSUBSCRIBER_TAG_CURRENT);
        $activeTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_ACTIVE);

        $this->assertEquals(2, count($currentTags));
        $this->assertEquals(2, count($activeTags));

    }

    public function testModifySubscriberTags(): void
    {
        $subscriberCurrentTags = ['current1','current2'];
        $subscriberNewTags = ['new1','new2'];
        // Store the subscriber for the test api client to provide mock responses
        TestMailchimpApiClient::setSubscriber([
            'fname' => $this->test_fname,
            'lname' => $this->test_fname,
            'email' => $this->test_email,
            'tags' => $subscriberCurrentTags
        ]);

        // MailchimpSubscriber record values
        $record = [
            'Name' => "{$this->test_fname} {$this->test_lname}",
            'Email' => $this->test_email,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($subscriberNewTags)
        ];

        $subscriber = MailchimpSubscriber::create($record);
        $subscriber->write();

        $subscriber->modifySubscriberTags();

        $currentTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMPSUBSCRIBER_TAG_CURRENT);
        $activeTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_ACTIVE);

        $this->assertEquals(2, count($currentTags));
        foreach($currentTags as $currentTag) {
            $this->assertTrue(in_array($currentTag['name'], $subscriberCurrentTags));
        }

        $this->assertEquals(2, count($activeTags));
        foreach($activeTags as $activeTag) {
            $this->assertTrue(in_array($activeTag['name'], $subscriberNewTags));
        }
    }

    public function testRemoveModifySubscriberTags(): void
    {

        Config::modify()->set(MailchimpSubscriber::class, 'remove_subscriber_tags', true);

        $subscriberCurrentTags = ['current1','current2'];
        $subscriberNewTags = ['new1','new2'];
        // Store the subscriber for the test api client to provide mock responses
        TestMailchimpApiClient::setSubscriber([
            'fname' => $this->test_fname,
            'lname' => $this->test_fname,
            'email' => $this->test_email,
            'tags' => $subscriberCurrentTags
        ]);

        // MailchimpSubscriber record values
        $record = [
            'Name' => "{$this->test_fname} {$this->test_lname}",
            'Email' => $this->test_email,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($subscriberNewTags)
        ];

        $subscriber = MailchimpSubscriber::create($record);
        $subscriber->write();

        $subscriber->modifySubscriberTags();

        $currentTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMPSUBSCRIBER_TAG_CURRENT);
        $activeTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_ACTIVE);
        $inactiveTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_INACTIVE);

        $this->assertEquals(2, count($currentTags));
        foreach($currentTags as $currentTag) {
            $this->assertTrue(in_array($currentTag['name'], $subscriberCurrentTags));
        }

        $this->assertEquals(2, count($activeTags));
        foreach($activeTags as $activeTag) {
            $this->assertTrue(in_array($activeTag['name'], $subscriberNewTags));
        }

        // tags marked for removal as remove_subscriber_tags switch to true
        $this->assertEquals(2, count($inactiveTags));
        foreach($inactiveTags as $inactiveTag) {
            $this->assertTrue(in_array($inactiveTag['name'], $subscriberCurrentTags));
        }
    }

    public function testRetainModifySubscriberTags(): void
    {

        $subscriberCurrentTags = ['current1','current2'];
        $subscriberNewTags = ['current2','new1','new2'];//retain current1
        // Store the subscriber for the test api client to provide mock responses
        TestMailchimpApiClient::setSubscriber([
            'fname' => $this->test_fname,
            'lname' => $this->test_fname,
            'email' => $this->test_email,
            'tags' => $subscriberCurrentTags
        ]);

        // MailchimpSubscriber record values
        $record = [
            'Name' => "{$this->test_fname} {$this->test_lname}",
            'Email' => $this->test_email,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($subscriberNewTags)
        ];

        $subscriber = MailchimpSubscriber::create($record);
        $subscriber->write();

        $subscriber->modifySubscriberTags();

        $currentTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMPSUBSCRIBER_TAG_CURRENT);
        $activeTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_ACTIVE);
        $inactiveTags = $subscriber->getTagDelta(MailchimpSubscriber::MAILCHIMP_TAG_INACTIVE);

        $this->assertEquals(2, count($currentTags));
        foreach($currentTags as $currentTag) {
            $this->assertTrue(in_array($currentTag['name'], $subscriberCurrentTags));
        }

        $this->assertEquals(3, count($activeTags));
        foreach($activeTags as $activeTag) {
            $this->assertTrue(in_array($activeTag['name'], $subscriberNewTags));
        }

        $this->assertEquals(0, count($inactiveTags));
    }
}
