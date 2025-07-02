<?php

namespace NSWDPC\Chimple\Tests;

use DrewM\MailChimp\MailChimp as MailchimpApiClient;
use NSWDPC\Chimple\Services\Logger;
use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Dev\SapphireTest;

/**
 * Unit test to verify subscription handling via the API
 * @author James
 */
class ChimpleSubscriberTest extends SapphireTest
{
    use Configurable;

    protected $usesDatabase = true;

    /**
     * e.g example.com
     * @var string
     */
    private static $test_email_domain = "";

    /**
     * e.g bob.smith
     * @var string
     */
    private static $test_email_user = "";

    /**
     * use plus notation in email address
     * @var bool
     */
    private static $test_use_plus = true;

    public function testSubscriber()
    {
        $fname = "Test";
        $lname = "Tester";

        $test_email_domain = $this->config()->get('test_email_domain');
        $test_email_user = $this->config()->get('test_email_user');
        $test_use_plus = $this->config()->get('test_use_plus');

        if (!$test_email_user) {
            throw new \Exception("The test needs a test email user");
        }

        if (!$test_email_domain) {
            throw new \Exception("The test needs a test email domain");
        }

        // store current value
        $list_id = Config::inst()->get(MailchimpConfig::class, 'list_id');

        // set a obfuscation chr
        Config::modify()->set(MailchimpSubscriber::class, 'obfuscation_chr', "•");

        $obfuscation_chr = Config::inst()->get(MailchimpSubscriber::class, 'obfuscation_chr');

        $email_address_for_test = $test_email_user;
        if ($test_use_plus) {
            $email_address_for_test .= "+unittest" . bin2hex(random_bytes(2));
        }
        $email_address_for_test .= "@{$test_email_domain}";

        $tags = ['TestOne','TestTwo'];
        $record = [
            'Name' => "{$fname} {$lname}",
            'Email' => $email_address_for_test,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'TagsValue' => json_encode($tags)
        ];
        $subscriber =  MailchimpSubscriber::create($record);
        $subscriber->write();

        $this->assertTrue($subscriber->exists(), "Subscriber does not exist");

        $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_NEW, "Subscriber should be 'new' status");

        $this->assertEquals($subscriber->Name, $fname, "Subscriber should have fname of {$fname}");

        $this->assertEquals($subscriber->Surname, $lname, "Subscriber shold have lname of {$lname}");

        $client = MailchimpSubscriber::api();

        $this->assertTrue($client instanceof MailchimpApiClient, "Client is not a valid mailchimp instance");

        // test an API subscription
        $this->assertEquals($list_id, $subscriber->getMailchimpListId(), "List id should be the default list id");

        $subscribe_record = $subscriber->getSubscribeRecord();

        $this->assertTrue(is_array($subscribe_record), "Record is not an array of values");

        $this->assertTrue(!empty($subscribe_record), "Record is empty");

        $this->assertTrue(isset($subscribe_record['merge_fields']), "Record merge_fields is not set");

        $this->assertTrue(!empty($subscribe_record['email_type']), "Record email_type is empty");

        $this->assertEquals($subscriber->Email, $subscribe_record['email_address'], "Subscribed email_address value is not the same as subsciber record Email field value");

        // check merge fields
        $sync_fields = $subscriber->config()->get('sync_fields');
        $merge_fields = $subscribe_record['merge_fields'];
        foreach ($sync_fields as $field => $tag) {
            $this->assertTrue(isset($merge_fields[ $tag ]) && $merge_fields[ $tag ] = $subscriber->getField($field), "Merge field tag {$tag} value does not match subscriber {$field} value");
        }

        $email = $subscriber->Email;

        if ($subscriber->subscribe()) {

            $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS, "Status of subscriber should be subscribed");
            // check ID matches md5
            $this->assertEquals(md5(strtolower($email)), $subscriber->SubscribedId, "Email does not match returned id {$subscriber->SubscribedId}");

            $this->assertNotEmpty($subscriber->SubscribedWebId, "SubscribedWebId should not be empty");

            $this->assertNotEmpty($subscriber->SubscribedUniqueEmailId, "SubscribedUniqueEmailId should not be empty");

            $this->assertTrue(substr_count($subscriber->Email, $obfuscation_chr) > 0, "Email is not obfuscated, it should be");

            $this->assertTrue(substr_count($subscriber->Name, $obfuscation_chr) > 0, "Name is not obfuscated, it should be");

            $this->assertTrue(substr_count($subscriber->Surname, $obfuscation_chr) > 0, "Surname is not obfuscated, it should be");

            $mc_record = MailchimpSubscriber::checkExistsInList($list_id, $email);

            $this->assertTrue(!empty($mc_record['id']), "The subscriber does not exist in the list {$list_id} - it should");
            $this->assertEquals(MailchimpSubscriber::MAILCHIMP_SUBSCRIBER_PENDING, $mc_record['status'], "The subscriber is not pending status");

            // check tags
            $this->assertEquals(count($tags), count($mc_record['tags']), "Tag count mismatch");

            $mc_tags_list = [];
            array_walk($mc_record['tags'], function ($value, $key) use (&$mc_tags_list) {
                $mc_tags_list[] = $value['name'];
            });

            sort($tags);
            sort($mc_tags_list);

            $this->assertEquals($tags, $mc_tags_list, "Tags sent do not match tags retrieved");

        } else {

            // failed
            $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_FAIL, "Status of subscriber should be failed");

            $this->assertNotEmpty($subscriber->LastError, "Last error should have some information");

            // the values should be empty
            $this->assertEmpty($subscriber->SubscribedUniqueEmailId, "SubscribedUniqueEmailId should be empty");

            $this->assertEmpty($subscriber->SubscribedWebId, "SubscribedWebId should be empty");

            $this->assertEmpty($subscriber->SubscribedId, "SubscribedId should be empty");

            $mc_record = MailchimpSubscriber::checkExistsInList($list_id, $email);
            $this->assertTrue(empty($mc_record['id']), "The subscriber exists in the list {$list_id} it should not");

        }

    }

}
