<?php

namespace NSWDPC\Chimple\Models;

use DrewM\MailChimp\MailChimp as MailchimpApiClient;
use NSWDPC\Chimple\Services\Logger;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use Exception;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\Fields\MultiValueTextField;

class MailchimpSubscriber extends DataObject implements PermissionProvider
{
    const CHIMPLE_STATUS_UNKNOWN = '';
    const CHIMPLE_STATUS_NEW = 'NEW';
    const CHIMPLE_STATUS_PROCESSING = 'PROCESSING';
    const CHIMPLE_STATUS_BATCHED = 'BATCHED';
    const CHIMPLE_STATUS_SUCCESS = 'SUCCESS';
    const CHIMPLE_STATUS_FAIL = 'FAIL';

    const MAILCHIMP_SUBSCRIBER_PENDING = 'pending';
    const MAILCHIMP_SUBSCRIBER_SUBSCRIBED = 'subscribed';
    const MAILCHIMP_SUBSCRIBER_UNSUBSCRIBED = 'unsubscribed';
    const MAILCHIMP_SUBSCRIBER_CLEANED = 'cleaned';

    const MAILCHIMP_TAG_INACTIVE = 'inactive';
    const MAILCHIMP_TAG_ACTIVE = 'active';

    const MAILCHIMP_EMAIL_TYPE_HTML = 'html';
    const MAILCHIMP_EMAIL_TYPE_TEXT = 'text';

    /**
     * @var string
     */
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

    /**
     * Email type, defaults to 'html', other value is 'text'
     * @var string
     */
    private static $email_type = self::MAILCHIMP_EMAIL_TYPE_HTML;

    /**
     * Remove tags that do not exist in the tags list when a subscriber
     * attempts to update their subscription
     * This is a potentially destructive action as it will remove tags added to
     * a subscriber via other means
     * @var string
     */
    private static $remove_subscriber_tags = false;

    /**
     * @var array
     */
    private static $db = [
        'Name' => 'Varchar(255)',
        'Surname' => 'Varchar(255)',
        'Email' => 'Varchar(255)',
        'FailNoticeSent' => 'Boolean',
        'MailchimpListId' => 'Varchar(255)',//list id the subscriber was subscribed to
        'LastError' => 'Text',
        'Status' => 'Varchar(12)',// arbitrary status (see const CHIMPLE_STATUS_*)
        'UpdateExisting' => 'Boolean',// @deprecated
        'SendWelcome' => 'Boolean',// @deprecated
        'ReplaceInterests' => 'Boolean',// @deprecated
        'DoubleOptIn' => 'Boolean',// @deprecated

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
        'UpdateExisting' => 1,// @deprecated
        'SendWelcome' => 0,// @deprecated
        'ReplaceInterests' => 0,// @deprecated
        'DoubleOptIn' => 1// @deprecated
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

    /**
     * @var MailchimpApiClient
     * The Mailchimp API client instance
     */
    protected static $mailchimp = null;

    /**
     * List of current subscriber tags
     * @var array|null
     */
    private $_cache_tags = null;


    /**
     * Return whether subscription was success
     */
    public function getSuccessful()
    {
        return $this->Status == self::CHIMPLE_STATUS_SUCCESS;
    }
    /**
     * @inheritdoc
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // remove deprecated fields
        $fields->removeByName([
            'UpdateExisting',
            'SendWelcome',
            'ReplaceInterests',
            'DoubleOptIn'
        ]);

        $fields->removeByName([
            'FailNoticeSent'
        ]);

        $fields->makeFieldReadOnly(
            $fields->dataFieldByName('LastError')
            ->setDescription(
                _t(
                    __CLASS__. '.IF_ERROR_OCCURRED',
                    "If a subscription error occurred, this will display the last error returned by Mailchimp and can help determine the cause of the error"
                )
            )
        );

        // status field
        if($this->exists()) {
            $status_field = DropdownField::create(
                'Status',
                _t(__CLASS__ . '.STATUS', 'Status'),
                [
                    self::CHIMPLE_STATUS_UNKNOWN => '',
                    self::CHIMPLE_STATUS_NEW => _t(__CLASS__ . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_PROCESSING => _t(__CLASS__ . '.STATUS_PROCESSING', 'PROCESSING (the subscriber is in the process of being subscribed)'),
                    self::CHIMPLE_STATUS_SUCCESS => _t(__CLASS__ . '.STATUS_SUCCESS', 'SUCCESS (the subscriber was subscribed)'),
                    self::CHIMPLE_STATUS_FAIL => _t(__CLASS__ . '.STATUS_FAIL', 'FAIL (the subscriber could not be subscribed - check the \'Last Error\' value)')
                ]
            );

            // only failed, empty or stuck processing subscription status can have their status changed
            if($this->Status != self::CHIMPLE_STATUS_FAIL
                && $this->Status != self::CHIMPLE_STATUS_PROCESSING
                && !empty($this->Status)) {
                // these status are readonly in CMS fields
                $status_field = $status_field->performReadonlyTransformation();
            } else if($this->Status == self::CHIMPLE_STATUS_PROCESSING) {
                // stuck processing - can reset to new
                $status_field->setDescription(
                    _t(
                        __CLASS__ . '.PROCESSING_RESET_NEW_STATUS',
                        "If this attempt is stuck, reset to 'New' for another attempt"
                    )
                );
                // can retain failed or set to new for retry
                $status_field->setSource([
                    self::CHIMPLE_STATUS_NEW => _t(__CLASS__ . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_PROCESSING =>  _t(__CLASS__ . '.STATUS_PROCESSING', 'PROCESSING (the subscriber is in the process of being subscribed)'),
                ]);
            } else if($this->Status == self::CHIMPLE_STATUS_FAIL) {
                // handling when failed
                $status_field->setDescription(
                    _t(
                        __CLASS__ . '.FAIL_RESET_NEW_STATUS',
                        "Reset this value to 'New' to retry a failed subscription attempt"
                    )
                );
                // can retain failed or set to new for retry
                $status_field->setSource([
                    self::CHIMPLE_STATUS_NEW => _t(__CLASS__ . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_FAIL => _t(__CLASS__ . '.STATUS_FAIL', 'FAIL (the subscriber could not be subscribed - check the \'Last Error\' value)')
                ]);
            }

            $fields->addFieldToTab(
                'Root.Main',
                $status_field,
                'LastError'
            );

        } else {
            // does not exist yet
            $fields->removeByName([
                'Status'
            ]);
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'StatusForNew',
                    '<p class="message notice">'
                    . _t(
                        __CLASS__ . '.STATUS_NEW_MESSAGE',
                        'This subscription attempt record will be given status of \'New\' and it will enter the pending subscription queue upon save'
                    )
                    . '</p>'
                ),
                'Name'
            );
        }

        $tags = $this->getCurrentSubscriberTags();
        $tag_field_description = "";
        if(!empty($tags)) {
            $tag_field_description = _t(
                __CLASS__ . '.TAGS_FIELD_DESCRIPTION',
                'The current tags for this subscriber are <code>{tags}</code><br>Tags not in the tag update list will be removed. New tags will be added.',
                [
                    'tags' => implode(", ", $tags)
                ]
            );
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                KeyValueField::create(
                    'MergeFields',
                    _t(
                        __CLASS__ . '.MERGE_FIELDS',
                        'Merge fields for this subscription attempt'
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
                        'Tag update list'
                    )
                )->setDescription(
                    $tag_field_description
                )
            ]
        );

        // get profile link
        $dc = MailChimpConfig::getDataCentre();
        if($dc && $this->SubscribedWebId) {
            $subscriber_profile_link = "https://{$dc}.admin.mailchimp.com/lists/members/view?id={$this->SubscribedWebId}";
        } else {
            $subscriber_profile_link = "n/a";
        }

        $readonly_fields = [
            'MailchimpListId' => _t(__CLASS__ . '.SUBSCRIBER_LIST_ID', "The Mailchimp List (audience) Id for this subscription record"),
            'SubscribedUniqueEmailId' => _t(__CLASS__ . '.SUBSCRIBER_UNIQUE_EMAIL_ID', "An identifier for the address across all of Mailchimp."),
            'SubscribedWebId' => _t(__CLASS__ . '.SUBSCRIBER_WEB_ID', "Member profile page: {link}", ['link' => $subscriber_profile_link]),
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

        if(!$this->exists()) {
            // new subscribers have a new status by default
            $this->Status = self::CHIMPLE_STATUS_NEW;
        }

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
        return trim($this->LastError ?? '') !== '' ? "yes" : "no";
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
     * @return MailchimpApiClient
     */
    public static function api() : MailchimpApiClient {
        // already set up..
        if(self::$mailchimp instanceof MailchimpApiClient) {
            return self::$mailchimp;
        }
        $api_key = MailchimpConfig::getApiKey();
        if (!$api_key) {
            throw new Exception("No Mailchimp API Key configured!");
        }
        self::$mailchimp = new MailchimpApiClient($api_key);
        return self::$mailchimp;
    }

    /**
     * @deprecated use self::api() instead
     */
    public function getMailchimp() {
        return self::api();
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
            $value = $this->getField($field);
            if(!is_string($value)) {
                $value = '';
            } else {
                $value = trim($value);
            }
            if ($value === '') {
                // do not set empty values, MailChimp does not like this
                // "The resource submitted could not be validated"
                continue;
            }
            $tag = strtoupper(trim($tag));
            $merge_fields[$tag] = $value;
        }

        return $merge_fields;
    }

    /**
     * Get the default subscription record data for adding/updating member in list
     * @return array
     */
    public function getSubscribeRecord() : array {
        // merge fields to send
        $merge_fields = $this->applyMergeFields();
        // ensure sane email type either html or text, default html if invalid
        $email_type = $this->config()->get('email_type');
        if(!$email_type || $email_type != self::MAILCHIMP_EMAIL_TYPE_HTML || $email_type != self::MAILCHIMP_EMAIL_TYPE_TEXT) {
            $email_type = self::MAILCHIMP_EMAIL_TYPE_HTML;
        }
        $params = [
            'email_address' => $this->Email,
            'email_type' => $email_type,
            'merge_fields' => $merge_fields
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
     * Get the hash that is used as the MC subscribed Id value
     * @return string
     */
    public static function getMailchimpSubscribedId($email) {
        if(!is_string($email) || !$email) {
            return '';
        } else {
            return MailchimpApiClient::subscriberHash($email);
        }
    }

    /**
     * Check if a given email address exists in the given list (audience)
     * @param string $list_id the Audience ID
     * @param string $email an email address, this is hashed using the MC hashing strategy
     * @param string $api_key @deprecated
     * @return boolean|array
     */
    public static function checkExistsInList(string $list_id, string $email, string $api_key = '') {

        // sanity check on input
        if(!$email) {
            throw new \Exception(
                _t(
                    __CLASS__ . ".EMAIL_NOT_PROVIDED",
                    "Please provide an email address"
                )
            );
        }

        if(!$list_id) {
            throw new \Exception(
                _t(
                    __CLASS__ . ".AUDIENCE_NOT_PROVIDED",
                    "Please provide a Mailchimp audience/list ID"
                )
            );
        }

        // attempt to get the subscriber
        if( $hash = self::getMailchimpSubscribedId($email) ) {
            $result = self::api()->get(
                "lists/{$list_id}/members/{$hash}"
            );
            // an existing member will return an 'id' value matching the hash
            // id = The MD5 hash of the lowercase version of the list member's email address.
            if(isset($result['id']) && $result['id'] == $hash) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Subscribe *this* particular record
     * @return bool
     */
    public function subscribe()
    {
        try {

            $last_error = "";
            $list_id = $this->getMailchimpListId();
            if (!$list_id) {
                throw new Exception("No Mailchimp List/Audience Id configured!");
            }

            if(!Email::is_valid_address($this->Email)) {
                throw new Exception("The email address provided for this record is not valid. See RFC822 spec.");
            }

            /**
             * Check if the subscriber already exists in this list
             */
            $existing = self::checkExistsInList($list_id, $this->Email);

            $result = false;

            if(!$existing) {
                $operation_path = "lists/{$list_id}/members";
                $params = $this->getSubscribeRecord();
                // add tags
                $params['tags'] = $this->getSubscriberTags();
                // new members are pending
                $params['status'] = self::MAILCHIMP_SUBSCRIBER_PENDING;

                // Attempt subscription of new list member
                $result = self::api()->post($operation_path, $params);
                $succeeded = !empty($result['unique_email_id']);

            } else {
                $operation_path = "lists/{$list_id}/members/{$existing['id']}";
                $params = $this->getSubscribeRecord();
                // Use current status
                $params['status'] = $existing['status'];
                // Attempt update of subscription of current list member
                $result = self::api()->patch($operation_path, $params);
                $succeeded = !empty($result['unique_email_id']);
                // modify tags on success
                if ($succeeded) {
                    $this->modifySubscriberTags();
                }
            }

            if ($succeeded) {
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
     * Return all current tags for a subscriber and handle pagination at the time of the API call
     * @param bool $force whether to get the tags from the remote or use previously retrieved tags
     * @param int $count the number of records to return per request
     * @return array an array of values, each value is a string tag
     */
    private function getCurrentSubscriberTags(bool $force = false, int $count = 10) : array {

        // if already retrieved,
        if(is_array($this->_cache_tags) && !$force) {
            return $this->_cache_tags;
        }

        $list_id = $this->MailchimpListId;
        $subscriber_hash = self::getMailchimpSubscribedId($this->Email ?? '');

        if(!$list_id || !$subscriber_hash) {
            $this->_cache_tags = null;
            return [];
        }

        $list = [];
        $offset = 0;
        $operation_path = "lists/{$list_id}/members/{$subscriber_hash}/tags/?count={$count}&offset={$offset}";

        $result = self::api()->get($operation_path);
        $total = isset($result['total_items']) ? $result['total_items'] : 0;
        // initial set of tags
        $tags = isset($result['tags']) && is_array($result['tags']) ? $result['tags'] : [];

        // populate the list of tags
        $walker = function($value, $key) use (&$list) {
            $list[] = $value['name'];
        };
        array_walk($tags, $walker);

        if($total > $count) {
            // e.g if 11 returned, there are 2 pages with 10 per page
            $pages = ceil($total / $count);
            $p = 1;
            while($p < $pages) {
                // update offset
                $offset = $p * $count;
                $operation_path = "lists/{$list_id}/members/{$subscriber_hash}/tags/?count={$count}&offset={$offset}";
                $result = self::api()->get($operation_path);
                $tags = isset($result['tags']) && is_array($result['tags']) ? $result['tags'] : [];
                array_walk($tags, $walker);
                $p++;
            }
        }

        $this->_cache_tags = $list;
        return $list;
    }

    /**
     * Modify this subscriber's tags based on their current tags
     */
    protected function modifySubscriberTags() : bool {

        $current = $this->getCurrentSubscriberTags();
        $tags_for_update = $this->getSubscriberTags();

        $params = [];
        $params['tags'] = [];

        // if enabled: remove tags that do not exist in the modification list
        if($this->config()->get('remove_subscriber_tags')) {
            $inactive = array_diff($current,$tags_for_update);
            // Mark removed tags as inactive
            foreach($inactive as $tag) {
                $params['tags'][] = [
                    'name' => $tag,
                    'status' => self::MAILCHIMP_TAG_INACTIVE
                ];
            }
        }

        // Retain active tags that are in both lists
        $retained = array_intersect($current,$tags_for_update);
        foreach($retained as $tag) {
            $params['tags'][] = [
                'name' => $tag,
                'status' => self::MAILCHIMP_TAG_ACTIVE
            ];
        }

        // DOCS
        // "If a tag that does not exist is passed in and set as 'active', a new tag will be created."
        $new = array_diff($tags_for_update, $current);
        foreach($new as $tag) {
            $params['tags'][] = [
                'name' => $tag,
                'status' => self::MAILCHIMP_TAG_ACTIVE
            ];
        }

        // operating on the current record
        $list_id = $this->MailchimpListId;
        $subscriber_hash = self::getMailchimpSubscribedId($this->Email ?? '');
        $operation_path = "/lists/{$list_id}/members/{$subscriber_hash}/tags";

        // submit payload to API
        $result = self::api()->post(
            $operation_path,
            $params
        );

        if($error = self::api()->getLastError()) {
            Logger::log("FAIL:{$error} List:{$list_id}", 'WARNING');
            return false;
        } else {
            return true;
        }
    }

    /**
     * Batch subscribe via API - hit from MailchimpSubscribeJob
     * Retrieve all subscribers marked new and attempt to subscribe them
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
                    Logger::log("REPORT_ONLY: would subscribe #{$subscriber->ID} to list {$subscriber->MailchimpListId}", 'DEBUG');
                }
                return $results;
            }

            // no report_only
            foreach ($subscribers as $subscriber) {
                // set in processing status
                $subscriber->Status = self::CHIMPLE_STATUS_PROCESSING;
                $subscriber->write();
                // attempt subscription
                $subscribe = $subscriber->subscribe();

                if (!isset($results[ $subscriber->Status ])) {
                    // set status to 0
                    $results[ $subscriber->Status ] = 0;
                }
                // increment this status
                $results[ $subscriber->Status ]++;
            }
            return $results;
        } catch (Exception $e) {
            Logger::log("FAIL: could not batch_subscribe, error=" . $e->getMessage(), 'NOTICE');
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
