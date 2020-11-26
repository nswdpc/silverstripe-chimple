<?php

namespace NSWDPC\Chimple\Models;

use DrewM\MailChimp\MailChimp as MailchimpApiClient;
use NSWDPC\Chimple\Services\Logger;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use Exception;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\Fields\MultiValueTextField;

class MailchimpSubscriber extends DataObject implements PermissionProvider
{
    const CHIMPLE_STATUS_NEW = 'NEW';
    const CHIMPLE_STATUS_PROCESSING = 'PROCESSING';
    const CHIMPLE_STATUS_BATCHED = 'BATCHED';
    const CHIMPLE_STATUS_SUCCESS = 'SUCCESS';
    const CHIMPLE_STATUS_FAIL = 'FAIL';

    const MAILCHIMP_SUBSCRIBER_PENDING = 'pending';
    const MAILCHIMP_SUBSCRIBER_SUBSCRIBED = 'subscribed';
    const MAILCHIMP_SUBSCRIBER_UNSUBSCRIBED = 'unsubscribed';
    const MAILCHIMP_SUBSCRIBER_CLEANED = 'cleaned';

    private static $table_name = 'ChimpleSubscriber';

    /**
     * Singular name for CMS
     * @var string
     */
    private static $singular_name = 'Mailchimp Subscriber';

    /**
     * Plural name for CMS
     * @var string
     */
    private static $plural_name = 'Mailchimp Subscribers';

    /**
     * Default sort ordering
     * @var array
     */
    private static $default_sort = ['Created' => 'DESC'];

    /**
     * Default chr for obfuscation
     * @var array
     */
    private static $obfuscation_chr = "•";

    private static $db = [
        'Name' => 'Varchar(255)',
        'Surname' => 'Varchar(255)',
        'Email' => 'Varchar(255)',
        'FailNoticeSent' => 'Boolean',
        'MailchimpListId' => 'Varchar(255)',//list id the subscriber was subscribed to
        'LastError' => 'Text',
        'Status' => 'Varchar(12)',// arbitrary status (see const CHIMPLE_STATUS_*)
        // these values are set when subscribe record is created
        'UpdateExisting' => 'Boolean',
        'SendWelcome' => 'Boolean',
        'ReplaceInterests' => 'Boolean',
        'DoubleOptIn' => 'Boolean',

        // for responses
        'SubscribedUniqueEmailId' => 'Varchar(255)',
        'SubscribedWebId' => 'Varchar(255)',
        'SubscribedId' => 'Varchar(32)',//The MD5 hash of the lowercase version of the list member's email address.

        // for storing arbitrary MergeFields
        'MergeFields' => 'MultiValueField',
        // for storing Tags for this subscriber
        'Tags' => 'MultiValueField'
    ];

    private static $sync_fields = [
        'Name' => 'FNAME',
        'Surname' => 'LNAME'
    ];

    private static $indexes = [
        'Status' => true,
        'Email' => true,
        'Name' => true,
        'Surname' => true,
        'MailchimpListId' => true,
        'SubscribedId' => true,
    ];

    /**
     * Add default values to database
     * @var array
     */
    private static $defaults = [
        'Status' => self::CHIMPLE_STATUS_NEW,
        'UpdateExisting' => 1,
        'SendWelcome' => 0,
        'ReplaceInterests' => 0,
        'DoubleOptIn' => 1 // by default everyone has double-opt-in
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'Created.Nice' => 'Created',
        'Name' => 'Name',
        'Surname' => 'Surname',
        'Email' => 'Email',
        'Created.Nice' => 'Created',
        //'FailNoticeSent.Nice' => 'Fail Notice Sent',
        'MailchimpListId' => 'List',
        'HasLastError' => 'Error?',
        'Status' => 'Status'
    ];

    /**
     * Defines a default list of filters for the search context
     * @var array
     */
    private static $searchable_fields = [
        'Name' => 'PartialMatchFilter',
        'Surname' => 'PartialMatchFilter',
        'Email' => 'PartialMatchFilter',
        'FailNoticeSent' => 'ExactMatchFilter',
        'Status' => 'PartialMatchFilter',
    ];

    private $mailchimp = null;// mailchimp API client

    /**
     * Return whether subscription was success
     */
    public function getSuccessful()
    {
        return $this->Status == self::CHIMPLE_STATUS_SUCCESS;
    }

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'FailNoticeSent'
        ]);

        $fields->makeFieldReadOnly(
            $fields->dataFieldByName('LastError')
            ->setDescription(
                _t(
                    __CLASS__. '.IF_ERROR_OCCURRED',
                    "If an error occurred, this will display the last error returned by Mailchimp"
                )
            )
        );

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'Status',
                _t(__CLASS__ . '.STATUS', 'Status'),
                [
                    self::CHIMPLE_STATUS_NEW => _t(__CLASS__ . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_PROCESSING => _t(__CLASS__ . '.STATUS_PROCESSING', 'PROCESSING (the subscriber is in the process of being subscribed)'),
                    self::CHIMPLE_STATUS_SUCCESS => _t(__CLASS__ . '.STATUS_SUCCESS', 'SUCCESS (the subscriber was subscribed)'),
                    self::CHIMPLE_STATUS_FAIL => _t(__CLASS__ . '.STATUS_FAIL', 'FAIL (the subscriber could not be subscribed - check the \'Last Error\' value)')
                ]
            )->setEmptyString('')
            ->setDescription(
                _t(
                    __CLASS__ . '.USE_CARE_STATUS',
                    "Use care when resetting this value. E.g you can reset the status to NEW to attempt a re-subscription"
                )
            ),
            'LastError'
        );

        $fields->addFieldsToTab(
            'Root.Main',
            [
                KeyValueField::create(
                    'MergeFields',
                    _t(
                        __CLASS__ . '.MERGE_FIELDS',
                        'Merge fields for subscription'
                    )
                )->setDescription(
                    _t(
                        __CLASS__ . '.MERGE_FIELDS_DESCRIPTION',
                        'Keys are merge tag names, values are the values for this particular subscriber'
                    )
                ),
                MultiValueTextField::create(
                    'Tags',
                    _t(
                        __CLASS__ . '.TAGS_FIELD',
                        'Tags for this subscriber'
                    )
                )
            ]
        );

        $readonly_fields = [
            'MailchimpListId' => _t(__CLASS__ . '.SUBSCRIBER_LIST_ID', "The Mailchimp List (audience) Id for this subscription record"),
            'SubscribedUniqueEmailId' => _t(__CLASS__ . '.SUBSCRIBER_UNIQUE_EMAIL_ID', "An identifier for the address across all of Mailchimp."),
            'SubscribedWebId' => _t(__CLASS__ . '.SUBSCRIBER_WEB_ID', "The ID used in the Mailchimp web application. View this member in your Mailchimp account at https://{dc}.admin.mailchimp.com/lists/members/view?id={web_id}"),
            'SubscribedId' => _t(__CLASS__ . '.SUBSCRIBER_ID', "The MD5 hash of the lowercase version of the list member's email address.")
        ];

        foreach($readonly_fields as $readonly_field => $description) {
            $field = $fields->dataFieldByName($readonly_field);
            if($field) {
                $field = $field->performReadOnlyTransformation();
                if($description) {
                    $field->setDescription($description);
                }
                $fields->addFieldToTab(
                    'Root.Main',
                    $field
                );
            }
        }

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (empty($this->Surname)) {
            $this->Surname = $this->getSurnameFromName();
            // reset name
            $parts = explode(" ", $this->Name, 2);
            $this->Name = isset($parts[0]) ? $parts[0] : '';
        }

    }

    /**
     * Given some meta data, update the MergeFields for this subscriber
     * The parameter can contain values submitted via a form
     * By default MergeFields doesn't allow HTML tags as keys or as values
     * @param array $meta
     */
    public function updateMergeFields(array $meta) {
        if(empty($meta)) {
            return;
        }

        $data = [];
        foreach($meta as $k => $v) {
            if(!is_scalar($v)) {
                // ignore values that cannot be saved
                continue;
            }
            $key = strtoupper( trim( strip_tags($k) ) );
            $value = trim( strip_tags($v) );
            $data[ $key ] = $value;
        }
        $this->MergeFields = $data;
        $this->write();
    }

    public function HasLastError()
    {
        return trim($this->LastError) !== '' ? "yes" : "no";
    }

    /**
     * Attempt to get a surname from the name
     */
    public function getSurnameFromName()
    {
        $parts = explode(" ", $this->Name, 2);
        if (!empty($parts[1])) {
            return $parts[1];
        }
    }

    /**
     * Get the API client
     * @return DrewM\Mailchimp\Mailchimp
     */
    public function getMailchimp()
    {
        $api_key = MailchimpConfig::getApiKey();
        if (!$api_key) {
            throw new Exception("No Mailchimp API Key configured!");
        }
        $this->mailchimp = new MailchimpApiClient($api_key);
        return $this->mailchimp;
    }

    /**
     * Get this record's listid or the configured value if none set
     */
    public function getMailchimpListId()
    {
        $list_id = $this->getField('MailchimpListId');
        if (!$list_id) {
            $list_id = MailchimpConfig::getDefaultMailchimpListId();
        }
        return $list_id;
    }

    /**
     * Applies merge fields prior to subscription attempt
     * @return array
     */
    protected function applyMergeFields() {
        $merge_fields = [];

        // get subscriber meta data
        $meta = $this->MergeFields->getValue();
        if(is_array($meta) && !empty($meta)) {
            foreach($meta as $k => $v) {
                if($v === '') {
                    // do not set empty values, MailChimp does not like this
                    continue;
                }
                $k = strtoupper(trim($k));
                $merge_fields[$k] = trim($v);
            }
        }

        // now apply configured sync fields, which can override meta
        $sync_fields = $this->config()->get('sync_fields');

        foreach ($sync_fields as $field => $tag) {
            if (!$this->hasField($field)) {
                // ignore non-existent fields
                continue;
            }
            $value = trim($this->getField($field));
            if ($value === '') {
                // do not set empty values, MailChimp does not like this
                // "The resource submitted could not be validated"
                continue;
            }
            $tag = strtoupper(trim($tag));
            $merge_fields[$tag] = trim($value);
        }

        return $merge_fields;
    }

    /**
     *  @return array
     */
    public function getSubscribeRecord() {

        // merge fields to send
        $merge_fields = $this->applyMergeFields();

        // status: subscribed unsubscribed cleaned pending
        $double_optin = $this->DoubleOptIn == 1;
        if (!$double_optin) {
            // subscribed when doubleoptin is off
            $status = self::MAILCHIMP_SUBSCRIBER_SUBSCRIBED;
        } else {
            // pending when doubleoptin is on
            $status = self::MAILCHIMP_SUBSCRIBER_PENDING;
        }

        $params = [
            'email_address' => $this->Email,
            'status'        => $status,
            'double_optin' => $double_optin,
            'merge_fields' => $merge_fields,
            'update_existing' => $this->UpdateExisting,
            'replace_interests' => $this->ReplaceInterests,
            'send_welcome' => $this->SendWelcome,
            'tags' => $this->getSubscriberTags()
        ];

        return $params;
    }

    /**
     * Return tags for this subscriber
     */
    public function getSubscriberTags() {
        $tags = $this->Tags->getValue();
        if(!is_array($tags)) {
            return [];
        } else {
            return array_values($tags);
        }
    }

    /**
     * Called after a successful subscription, obfuscates email, name and surname
     * @return void
     */
    private function obfuscate() {
        $obfuscate = function($in) {
            $length = strlen($in);
            if($length == 0) {
                return "";
            }
            $chr = $this->config()->get('obfuscation_chr');
            if($chr === "") {
                // if no chr configured, do not obfuscate (e.g not require by project)
                return $in;
            }
            $sub_length = $length - 2;
            if($sub_length <= 0) {
                return str_repeat($chr, $length);
            }
            $replaced = substr_replace($in, str_repeat($chr, $sub_length), 1, $sub_length);
            return $replaced;
        };
        $this->Email = $obfuscate($this->Email);
        $this->Name = $obfuscate($this->Name);
        $this->Surname = $obfuscate($this->Surname);
    }

    /**
     * Get the has that is used as the MC subscribed Id value
     * @return string
     */
    public static function getMailchimpSubscribedId($email) {
        return md5(strtolower($email));
    }

    /**
     * Subscribe *this* particular record
     */
    public function subscribe()
    {
        try {
            $last_error = "";
            $list_id = $this->getMailchimpListId();
            if (!$list_id) {
                throw new Exception("No Mailchimp List/Audience Id configured!");
            }

            // Subscribe via API
            $api = $this->getMailchimp();
            $result = $api->post(
                "lists/{$list_id}/members",
                $this->getSubscribeRecord()
            );

            if (!empty($result['unique_email_id'])) {
                // this unique_email_id value is returned when subscribed
                $this->SubscribedUniqueEmailId = $result['unique_email_id'];
                $this->SubscribedWebId = $result['web_id'] ?? '';
                $this->SubscribedId = $result['id'] ?? '';
                $this->Status = self::CHIMPLE_STATUS_SUCCESS;
                $this->LastError = '';//reset any error
                // obfucsate values of subscriber after successful subscription
                $this->obfuscate();
                $this->write();
                return true;
            } elseif (!empty($result['status'])) {
                $error_detail = isset($result['detail']) ? $result['detail'] : '';
                $error_status = $result['status'];
                $error_title = $result['title'];
                $errors = "{$error_status}|{$error_title}|{$error_detail}";
            } else {
                $errors = "Unhandled error for email: {$this->Email}";
            }
            throw new Exception(trim($errors));
        } catch (Exception $e) {
            $last_error = $e->getMessage();
        }

        // record failure
        $this->SubscribedUniqueEmailId = '';
        $this->SubscribedWebId = '';
        $this->SubscribedId = '';
        $this->Status = self::CHIMPLE_STATUS_FAIL;
        $this->LastError = $last_error;
        $this->write();

        return false;
    }

    /**
     * Batch subscribe via API - hit from MailchimpSubscribeJob
     * @return array
     */
    public static function batch_subscribe($limit = 100, $report_only = false)
    {
        $results = [];
        try {

            // attempt to subscribe any "new" subscribers
            $subscribers = MailchimpSubscriber::get()->filter(['Status' => self::CHIMPLE_STATUS_NEW ]);
            if ($limit) {
                $subscribers = $subscribers->limit($limit);
            }
            if ($report_only) {
                $results[ self::CHIMPLE_STATUS_PROCESSING ] = $subscribers->count();
                foreach ($subscribers as $subscriber) {
                    Logger::log("Would subscribe #{$subscriber->ID} to list {$subscriber->MailchimpListId}", 'DEBUG');
                }
                return $results;
            }

            // no report_only
            if ($subscribers->count() > 0) {
                foreach ($subscribers as $subscriber) {
                    // set in processing status
                    $subscriber->Status = self::CHIMPLE_STATUS_PROCESSING;
                    $subscriber->write();
                    $subscribe = $subscriber->subscribe();

                    if (!isset($results[ $subscriber->Status ])) {
                        // set status to 0
                        $results[ $subscriber->Status ] = 0;
                    }
                    // increment this status
                    $results[ $subscriber->Status ]++;
                }
            }
            return $results;
        } catch (Exception $e) {
            Logger::log($e->getMessage(), 'NOTICE');
        }

        return false;
    }

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_VIEW');
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_EDIT');
    }

    /**
     * Only admin can delete subscribers
     */
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'ADMIN');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_CREATE');
    }

    public function providePermissions()
    {
        return [
            'MAILCHIMP_SUBSCRIBER_VIEW' => [
                'name' => _t(__CLASS__ . '.MAILCHIMP_SUBSCRIBER_VIEW', 'View Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_SUBSCRIBER_EDIT' => [
                'name' => _t(__CLASS__ . '.MAILCHIMP_SUBSCRIBER_EDIT', 'Edit Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_SUBSCRIBER_CREATE' => [
                'name' => _t(__CLASS__ . '.MAILCHIMP_SUBSCRIBER_CREATE', 'Create Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ]
        ];
    }
}
