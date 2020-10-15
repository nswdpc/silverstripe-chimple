<?php

namespace NSWDPC\Chimple\Tests;

use DrewM\MailChimp\MailChimp as MailchimpApiClient;
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

    private static $test_email_domain = "";// e.g example.com
    private static $test_email_user = "";// e.g bob.smith
    private static $test_use_plus = true;// use plus notation in email address

    public function testSubscriber()
    {
        $fname = "Test";
        $lname = "Tester";

        $test_email_domain = $this->config()->get('test_email_domain');
        $test_email_user = $this->config()->get('test_email_user');
        $test_use_plus = $this->config()->get('test_use_plus');

        if(!$test_email_user) {
            throw new \Exception("The test needs a test email user");
        }

        if(!$test_email_domain) {
            throw new \Exception("The test needs a test email domain");
        }

        // store current value
        $list_id = Config::inst()->get(MailchimpConfig::class, 'list_id');

        // set a obfuscation chr
        Config::inst()->update(MailchimpSubscriber::class, 'obfuscation_chr', "•");

        $obfuscation_chr = Config::inst()->get(MailchimpSubscriber::class, 'obfuscation_chr');

        $email_address_for_test = $test_email_user;
        if($test_use_plus) {
            $email_address_for_test .= "+unittest" . bin2hex(random_bytes(2));
        }
        $email_address_for_test .= "@{$test_email_domain}";

        $record = [
            'Name' => "{$fname} {$lname}",
            'Email' => $email_address_for_test,
            'FailNoticeSent' => 0,
            'Status' => MailchimpSubscriber::CHIMPLE_STATUS_NEW,
            'DoubleOptIn' => 0// turn off double option for test
        ];
        $subscriber =  MailchimpSubscriber::create($record);
        $subscriber->write();

        $this->assertTrue($subscriber->exists(), "Subscriber does not exist");

        $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_NEW, "Subscriber should be 'new' status");

        $this->assertEquals($subscriber->Name, $fname, "Subscriber should have fname of {$fname}");

        $this->assertEquals($subscriber->Surname, $lname, "Subscriber shold have lname of {$lname}");

        $client = $subscriber->getMailchimp();

        $this->assertTrue($client instanceof MailchimpApiClient, "Client is not a valid mailchimp instance");

        // test an API subscription
        $this->assertEquals($list_id, $subscriber->getMailchimpListId(), "List id should be the default list id");

        $subscribe_record = $subscriber->getSubscribeRecord();

        $this->assertTrue( is_array($subscribe_record), "Record is not an array of values");

        $this->assertTrue( !empty($subscribe_record), "Record is empty");

        $this->assertEquals( $subscriber->Email, $subscribe_record['email_address'], "Subscribed email_address value is not the same as subsciber record Email field value");

        $this->assertEquals( MailchimpSubscriber::MAILCHIMP_SUBSCRIBER_SUBSCRIBED, $subscribe_record['status'], "Subscribed email is not the same as subsciber record Email field value");

        $this->assertFalse( $subscribe_record['double_optin'], "Subscriber record double_optin value should be false/0");

        // check merge fields
        $sync_fields = $subscriber->config()->get('sync_fields');
        $merge_fields = $subscribe_record['merge_fields'];
        foreach($sync_fields as $field => $tag ) {
            $this->assertTrue( isset($merge_fields[ $tag ]) && $merge_fields[ $tag ] = $subscriber->getField($field), "Merge field tag {$tag} value does not match subscriber {$field} value");
        }

        $this->assertEquals( $subscriber->UpdateExisting, $subscribe_record['update_existing'], "Subscribed update_existing does not match record");

        $this->assertEquals( $subscriber->SendWelcome, $subscribe_record['send_welcome'], "Subscribed send_welcome does not match record");

        $this->assertEquals( $subscriber->ReplaceInterests, $subscribe_record['replace_interests'], "Subscribed replace_interests does not match record");

        $email = $subscriber->Email;
        if($subscriber->subscribe()) {

            $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_SUCCESS, "Status of subscriber should be subscribed");
            // check ID matches md5
            $this->assertEquals(md5(strtolower($email)), $subscriber->SubscribedId, "Email does not match returned id {$subscriber->SubscribedId}");

            $this->assertNotEmpty($subscriber->SubscribedWebId, "SubscribedWebId should not be empty");

            $this->assertNotEmpty($subscriber->SubscribedUniqueEmailId, "SubscribedUniqueEmailId should not be empty");

            $this->assertTrue(substr_count($subscriber->Email, $obfuscation_chr) > 0, "Email is not obfuscated, it should be");

            $this->assertTrue(substr_count($subscriber->Name, $obfuscation_chr) > 0, "Name is not obfuscated, it should be");

            $this->assertTrue(substr_count($subscriber->Surname, $obfuscation_chr) > 0, "Surname is not obfuscated, it should be");

        } else {

            // failed
            $this->assertEquals($subscriber->Status, MailchimpSubscriber::CHIMPLE_STATUS_FAIL, "Status of subscriber should be failed");

            $this->assertNotEmpty($subscriber->LastError, "Last error should have some information");

            // the values should be empty
            $this->assertEmpty($subscriber->SubscribedUniqueEmailId, "SubscribedUniqueEmailId should be empty");

            $this->assertEmpty($subscriber->SubscribedWebId, "SubscribedWebId should be empty");

            $this->assertEmpty($subscriber->SubscribedId, "SubscribedId should be empty");

        }

    }

}
