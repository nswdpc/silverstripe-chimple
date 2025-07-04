<?php

namespace NSWDPC\Chimple\Models;

use DrewM\MailChimp\MailChimp as MailchimpApiClient;
use NSWDPC\Chimple\Services\Logger;
use NSWDPC\Chimple\Services\ApiClientService;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Permission;
use Exception;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\Fields\MultiValueTextField;

/**
 * @property ?string $Name
 * @property ?string $Surname
 * @property ?string $Email
 * @property bool $FailNoticeSent
 * @property mixed $MailchimpListId
 * @property ?string $LastError
 * @property ?string $Status
 * @property bool $UpdateExisting
 * @property bool $SendWelcome
 * @property bool $ReplaceInterests
 * @property bool $DoubleOptIn
 * @property ?string $SubscribedUniqueEmailId
 * @property ?string $SubscribedWebId
 * @property ?string $SubscribedId
 * @property mixed $MergeFields
 * @property mixed $Tags
 */
class MailchimpSubscriber extends DataObject implements PermissionProvider
{
    public const CHIMPLE_STATUS_UNKNOWN = '';

    public const CHIMPLE_STATUS_NEW = 'NEW';

    public const CHIMPLE_STATUS_PROCESSING = 'PROCESSING';

    public const CHIMPLE_STATUS_BATCHED = 'BATCHED';

    public const CHIMPLE_STATUS_SUCCESS = 'SUCCESS';

    public const CHIMPLE_STATUS_FAIL = 'FAIL';

    public const MAILCHIMP_SUBSCRIBER_PENDING = 'pending';

    public const MAILCHIMP_SUBSCRIBER_SUBSCRIBED = 'subscribed';

    public const MAILCHIMP_SUBSCRIBER_UNSUBSCRIBED = 'unsubscribed';

    public const MAILCHIMP_SUBSCRIBER_CLEANED = 'cleaned';

    public const MAILCHIMP_TAG_INACTIVE = 'inactive';

    public const MAILCHIMP_TAG_ACTIVE = 'active';

    public const MAILCHIMPSUBSCRIBER_TAG_CURRENT = 'current';

    public const MAILCHIMP_EMAIL_TYPE_HTML = 'html';

    public const MAILCHIMP_EMAIL_TYPE_TEXT = 'text';

    private static string $table_name = 'ChimpleSubscriber';

    /**
     * Singular name for CMS
     */
    private static string $singular_name = 'Mailchimp Subscriber';

    /**
     * Plural name for CMS
     */
    private static string $plural_name = 'Mailchimp Subscribers';

    /**
     * Default sort ordering
     */
    private static string $default_sort = 'Created DESC';

    /**
     * Default chr for obfuscation
     */
    private static string $obfuscation_chr = "•";

    /**
     * Email type, defaults to 'html', other value is 'text'
     */
    private static string $email_type = self::MAILCHIMP_EMAIL_TYPE_HTML;

    /**
     * Remove tags that do not exist in the tags list when a subscriber
     * attempts to update their subscription
     * This is a potentially destructive action as it will remove tags added to
     * a subscriber via other means
     */
    private static bool $remove_subscriber_tags = false;

    /**
     * Store changes made in a subscribe attempt
     * This is reset prior to each subscribe() attempt
     */
    protected array $tagDelta = [];

    private static array $db = [
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

    private static array $sync_fields = [
        'Name' => 'FNAME',
        'Surname' => 'LNAME'
    ];

    private static array $indexes = [
        'Status' => true,
        'Email' => true,
        'Name' => true,
        'Surname' => true,
        'MailchimpListId' => true,
        'SubscribedId' => true,
    ];

    /**
     * Add default values to database
     */
    private static array $defaults = [
        'Status' => self::CHIMPLE_STATUS_NEW,
        'UpdateExisting' => 1,// @deprecated
        'SendWelcome' => 0,// @deprecated
        'ReplaceInterests' => 0,// @deprecated
        'DoubleOptIn' => 1// @deprecated
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     */
    private static array $summary_fields = [
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
     */
    private static array $searchable_fields = [
        'Name' => 'PartialMatchFilter',
        'Surname' => 'PartialMatchFilter',
        'Email' => 'PartialMatchFilter',
        'FailNoticeSent' => 'ExactMatchFilter',
        'Status' => 'PartialMatchFilter',
    ];

    /**
     * The Mailchimp API client instance
     */
    protected static ?MailchimpApiClient $mailchimp = null;

    /**
     * List of current subscriber tags
     */
    private ?array $_cache_tags = null;


    /**
     * Return whether subscription was success
     */
    public function getSuccessful(): bool
    {
        return $this->Status == self::CHIMPLE_STATUS_SUCCESS;
    }

    /**
     * @inheritdoc
     */
    #[\Override]
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
                    self::class. '.IF_ERROR_OCCURRED',
                    "If a subscription error occurred, this will display the last error returned by Mailchimp and can help determine the cause of the error"
                )
            )
        );

        // status field
        if ($this->exists()) {
            $status_field = DropdownField::create(
                'Status',
                _t(self::class . '.STATUS', 'Status'),
                [
                    self::CHIMPLE_STATUS_UNKNOWN => '',
                    self::CHIMPLE_STATUS_NEW => _t(self::class . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_PROCESSING => _t(self::class . '.STATUS_PROCESSING', 'PROCESSING (the subscriber is in the process of being subscribed)'),
                    self::CHIMPLE_STATUS_SUCCESS => _t(self::class . '.STATUS_SUCCESS', 'SUCCESS (the subscriber was subscribed)'),
                    self::CHIMPLE_STATUS_FAIL => _t(self::class . '.STATUS_FAIL', "FAIL (the subscriber could not be subscribed - check the 'Last Error' value)")
                ]
            );

            // only failed, empty or stuck processing subscription status can have their status changed
            if ($this->Status != self::CHIMPLE_STATUS_FAIL
                && $this->Status != self::CHIMPLE_STATUS_PROCESSING
                && !empty($this->Status)) {
                // these status are readonly in CMS fields
                $status_field = $status_field->performReadonlyTransformation();
            } elseif ($this->Status == self::CHIMPLE_STATUS_PROCESSING) {
                // stuck processing - can reset to new
                $status_field->setDescription(
                    _t(
                        self::class . '.PROCESSING_RESET_NEW_STATUS',
                        "If this attempt is stuck, reset to 'New' for another attempt"
                    )
                );
                // can retain failed or set to new for retry
                $status_field->setSource([
                    self::CHIMPLE_STATUS_NEW => _t(self::class . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_PROCESSING =>  _t(self::class . '.STATUS_PROCESSING', 'PROCESSING (the subscriber is in the process of being subscribed)'),
                ]);
            } elseif ($this->Status == self::CHIMPLE_STATUS_FAIL) {
                // handling when failed
                $status_field->setDescription(
                    _t(
                        self::class . '.FAIL_RESET_NEW_STATUS',
                        "Reset this value to 'New' to retry a failed subscription attempt"
                    )
                );
                // can retain failed or set to new for retry
                $status_field->setSource([
                    self::CHIMPLE_STATUS_NEW => _t(self::class . '.STATUS_NEW', 'NEW (the subscriber has not yet been subscribed)'),
                    self::CHIMPLE_STATUS_FAIL => _t(self::class . '.STATUS_FAIL', "FAIL (the subscriber could not be subscribed - check the 'Last Error' value)")
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
                        self::class . '.STATUS_NEW_MESSAGE',
                        "This subscription attempt record will be given status of 'New' and it will enter the pending subscription queue upon save"
                    )
                    . '</p>'
                ),
                'Name'
            );
        }

        $tags = $this->getCurrentSubscriberTags();
        $tag_field_description = "";
        if ($tags !== []) {
            $tag_field_description = _t(
                self::class . '.TAGS_FIELD_DESCRIPTION',
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
                        self::class . '.MERGE_FIELDS',
                        'Merge fields for this subscription attempt'
                    )
                )->setDescription(
                    _t(
                        self::class . '.MERGE_FIELDS_DESCRIPTION',
                        'Keys are merge tag names, values are the values for this particular subscriber'
                    )
                ),
                MultiValueTextField::create(
                    'Tags',
                    _t(
                        self::class . '.TAGS_FIELD',
                        'Tag update list'
                    )
                )->setDescription(
                    $tag_field_description
                )
            ]
        );

        // get profile link
        $dc = MailchimpConfig::getDataCentre();
        if ($dc && $this->SubscribedWebId) {
            $subscriber_profile_link = "https://{$dc}.admin.mailchimp.com/lists/members/view?id={$this->SubscribedWebId}";
        } else {
            $subscriber_profile_link = "n/a";
        }

        $readonly_fields = [
            'MailchimpListId' => _t(self::class . '.SUBSCRIBER_LIST_ID', "The Mailchimp List (audience) Id for this subscription record"),
            'SubscribedUniqueEmailId' => _t(self::class . '.SUBSCRIBER_UNIQUE_EMAIL_ID', "An identifier for the address across all of Mailchimp."),
            'SubscribedWebId' => _t(self::class . '.SUBSCRIBER_WEB_ID', "Member profile page: {link}", ['link' => $subscriber_profile_link]),
            'SubscribedId' => _t(self::class . '.SUBSCRIBER_ID', "The MD5 hash of the lowercase version of the list member's email address.")
        ];

        foreach ($readonly_fields as $readonly_field => $description) {
            $field = $fields->dataFieldByName($readonly_field);
            if ($field) {
                $field = $field->performReadOnlyTransformation();
                if ($description) {
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

    #[\Override]
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->exists()) {
            // new subscribers have a new status by default
            $this->Status = self::CHIMPLE_STATUS_NEW;
        }

        if (empty($this->Surname)) {
            $this->Surname = $this->getSurnameFromName();
            // reset name
            $parts = explode(" ", $this->Name ?? '', 2);
            $this->Name = $parts[0] ?? '';
        }

    }

    /**
     * Given some meta data, update the MergeFields for this subscriber
     * The parameter can contain values submitted via a form
     * By default MergeFields doesn't allow HTML tags as keys or as values
     */
    public function updateMergeFields(array $meta)
    {
        if ($meta === []) {
            return;
        }

        $data = [];
        foreach ($meta as $k => $v) {
            if (!is_scalar($v)) {
                // ignore values that cannot be saved
                continue;
            }

            $key = strtoupper(trim(strip_tags($k)));
            $value = trim(strip_tags($v));
            $data[ $key ] = $value;
        }

        $this->MergeFields = $data;
        $this->write();
    }

    public function HasLastError(): string
    {
        return trim($this->LastError ?? '') !== '' ? "yes" : "no";
    }

    /**
     * Attempt to get a surname from the name
     */
    public function getSurnameFromName(): ?string
    {
        $parts = explode(" ", $this->Name ?? '', 2);
        if (isset($parts[1]) && $parts[1] !== '') {
            return $parts[1];
        }

        return null;
    }

    /**
     * Get the API client
     */
    public static function api(): MailchimpApiClient
    {
        // already set up..
        if (self::$mailchimp instanceof MailchimpApiClient) {
            return self::$mailchimp;
        }

        $api_key = MailchimpConfig::getApiKey();
        if (!$api_key) {
            throw new Exception("No Mailchimp API Key configured!");
        }

        self::$mailchimp = ApiClientService::create()->getClient($api_key);
        return self::$mailchimp;
    }

    /**
     * @deprecated use self::api() instead
     */
    public function getMailchimp(): MailchimpApiClient
    {
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
     */
    protected function applyMergeFields(): array
    {
        $merge_fields = [];

        // get subscriber meta data
        $meta = $this->MergeFields->getValue();
        if (is_array($meta) && $meta !== []) {
            foreach ($meta as $k => $v) {
                if ($v === '') {
                    // do not set empty values, MailChimp does not like this
                    continue;
                }

                $k = strtoupper(trim($k));
                $merge_fields[$k] = trim((string) $v);
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
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                // do not set empty values, MailChimp does not like this
                // "The resource submitted could not be validated"
                continue;
            }

            $tag = strtoupper(trim((string) $tag));
            $merge_fields[$tag] = $value;
        }

        return $merge_fields;
    }

    /**
     * Get the default subscription record data for adding/updating member in list
     */
    public function getSubscribeRecord(): array
    {
        // merge fields to send
        $merge_fields = $this->applyMergeFields();
        // ensure sane email type either html or text, default html if invalid
        $email_type = self::config()->get('email_type') ?? '';
        if (!in_array($email_type, [self::MAILCHIMP_EMAIL_TYPE_HTML, self::MAILCHIMP_EMAIL_TYPE_TEXT])) {
            $email_type = self::MAILCHIMP_EMAIL_TYPE_HTML;
        }

        return [
            'email_address' => $this->Email ?? '',
            'email_type' => $email_type,
            'merge_fields' => $merge_fields
        ];
    }

    /**
     * Return tags for this subscriber
     */
    public function getSubscriberTags(): array
    {
        $tags = $this->Tags->getValue();
        if (!is_array($tags)) {
            return [];
        } else {
            return array_values($tags);
        }
    }

    /**
     * Called after a successful subscription, obfuscates email, name and surname
     * @return void
     */
    private function obfuscate()
    {
        $obfuscate = function (string $in): string {
            $length = strlen($in);
            if ($length == 0) {
                return "";
            }

            $chr = $this->config()->get('obfuscation_chr');
            if ($chr === "") {
                // if no chr configured, do not obfuscate (e.g not require by project)
                return $in;
            }

            $sub_length = $length - 2;
            if ($sub_length <= 0) {
                return str_repeat($chr, $length);
            }

            return substr_replace($in, str_repeat($chr, $sub_length), 1, $sub_length);
        };
        $this->Email = $obfuscate($this->Email ?? '');
        $this->Name = $obfuscate($this->Name ?? '');
        $this->Surname = $obfuscate($this->Surname ?? '');
    }

    /**
     * Get the hash that is used as the MC subscribed Id value
     * @return string
     */
    public static function getMailchimpSubscribedId(string $email): string
    {
        if ($email === '' || !Email::is_valid_address($email)) {
            return '';
        } else {
            return MailchimpApiClient::subscriberHash($email);
        }
    }

    /**
     * Check if a given email address exists in the given list (audience)
     * If an email exists in the list, return an array of subscriber data. If not, return false.
     * @param string $list_id the Audience ID
     * @param string $email an email address, this is hashed using the MC hashing strategy
     * @param string $api_key @deprecated
     */
    public static function checkExistsInList(string $list_id, string $email, string $api_key = ''): array|false
    {

        // sanity check on input
        if ($email === '' || !Email::is_valid_address($email)) {
            throw new \Exception(
                _t(
                    self::class . ".EMAIL_NOT_PROVIDED",
                    "Please provide an email address"
                )
            );
        }

        if ($list_id === '') {
            throw new \Exception(
                _t(
                    self::class . ".AUDIENCE_NOT_PROVIDED",
                    "Please provide a Mailchimp audience/list ID"
                )
            );
        }

        // attempt to get the subscriber
        if ($hash = self::getMailchimpSubscribedId($email)) {
            $result = self::api()->get(
                "lists/{$list_id}/members/{$hash}"
            );
            // an existing member will return an 'id' value matching the hash
            // id = The MD5 hash of the lowercase version of the list member's email address.
            if (isset($result['id']) && $result['id'] == $hash) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Return the changes to tags, primarily for tests
     * @param string $status filter by this status
     */
    public function getTagDelta(string $status = ''): array
    {
        if($status === '') {
            return $this->tagDelta;
        } else {
            return array_filter(
                $this->tagDelta,
                function($v, $k) use ($status) {
                    return $v['status'] == $status;
                },
                ARRAY_FILTER_USE_BOTH
            );
        }
    }

    /**
     * Subscribe *this* particular record
     */
    public function subscribe(): bool
    {
        try {

            $this->tagDelta = [];

            $last_error = "";
            $list_id = $this->getMailchimpListId();
            if (!$list_id) {
                throw new Exception("No Mailchimp List/Audience Id configured!");
            }

            $email = $this->Email ?? '';
            if (!Email::is_valid_address($email)) {
                throw new Exception("The email address provided for this record is not valid. See RFC822 spec.");
            }

            /**
             * Check if the subscriber already exists in this list
             */
            $existing = self::checkExistsInList($list_id, $email);

            $result = false;

            if ($existing === [] || $existing === false) {

                $operation_path = "lists/{$list_id}/members";
                $params = $this->getSubscribeRecord();
                // add tags
                $params['tags'] = $this->getSubscriberTags();
                $this->tagDelta = [];
                foreach($params['tags'] as $tagName) {
                    $this->tagDelta[] = [
                        'name' => $tagName,
                        'status' => self::MAILCHIMPSUBSCRIBER_TAG_CURRENT
                    ];
                }
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
                $error_detail = $result['detail'] ?? '';
                $error_status = $result['status'];
                $error_title = $result['title'];
                $errors = "{$error_status}|{$error_title}|{$error_detail}";
            } else {
                $errors = "Unhandled error for email: {$email}";
            }

            throw new Exception(trim($errors));
        } catch (Exception $exception) {
            $last_error = $exception->getMessage();
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
    public function getCurrentSubscriberTags(bool $force = false, int $count = 10): array
    {

        // if already retrieved,
        if (is_array($this->_cache_tags) && !$force) {
            return $this->_cache_tags;
        }

        $list_id = $this->MailchimpListId ?? '';
        $subscriber_hash = self::getMailchimpSubscribedId($this->Email ?? '');

        if (!$list_id || $subscriber_hash === '') {
            $this->_cache_tags = null;
            return [];
        }

        $list = [];
        $offset = 0;
        $operation_path = "lists/{$list_id}/members/{$subscriber_hash}/tags/?count={$count}&offset={$offset}";

        $result = self::api()->get($operation_path);
        $total = $result['total_items'] ?? 0;
        // initial set of tags
        $tags = isset($result['tags']) && is_array($result['tags']) ? $result['tags'] : [];

        // populate the list of tags
        $walker = function (array $value, $key) use (&$list): void {
            $list[] = $value['name'];
        };
        array_walk($tags, $walker);

        if ($total > $count) {
            // e.g if 11 returned, there are 2 pages with 10 per page
            $pages = ceil($total / $count);
            $p = 1;
            while ($p < $pages) {
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
    public function modifySubscriberTags(): bool
    {
        $this->tagDelta = [];

        // current set of tags linked to this subscriber
        $current = $this->getCurrentSubscriberTags();
        // Tags that have been assigned to this subscriber
        $tags_for_update = $this->getSubscriberTags();

        $params = [];
        $params['tags'] = [];

        /**
         * if enabled: remove tags that do not exist in the update list
         * See property documentation about effects
         */
        if ($this->config()->get('remove_subscriber_tags')) {
            $inactive = array_diff($current, $tags_for_update);
            // Mark removed tags as inactive
            foreach ($inactive as $tag) {
                $params['tags'][] = [
                    'name' => $tag,
                    'status' => self::MAILCHIMP_TAG_INACTIVE
                ];
            }
        }

        /**
         * Retain active tags that are in both lists
         */
        $retained = array_intersect($current, $tags_for_update);
        foreach ($retained as $tag) {
            $params['tags'][] = [
                'name' => $tag,
                'status' => self::MAILCHIMP_TAG_ACTIVE
            ];
        }

        /**
         * DOCS
         * If a tag that does not exist is passed in and set as 'active', a new tag will be created."
         */
        $new = array_diff($tags_for_update, $current);
        foreach ($new as $tag) {
            $params['tags'][] = [
                'name' => $tag,
                'status' => self::MAILCHIMP_TAG_ACTIVE
            ];
        }

        // operating on the current record
        $list_id = $this->MailchimpListId ?? '';
        $subscriber_hash = self::getMailchimpSubscribedId($this->Email ?? '');
        $operation_path = "lists/{$list_id}/members/{$subscriber_hash}/tags";

        // submit payload to API
        self::api()->post(
            $operation_path,
            $params
        );

        // store all tags, including current, for inspection
        $this->tagDelta = $params['tags'];
        foreach($current as $tag) {
            $this->tagDelta[] = [
                'name' => $tag,
                'status' => self::MAILCHIMPSUBSCRIBER_TAG_CURRENT
            ];
        }

        if ($error = self::api()->getLastError()) {
            Logger::log("FAIL:{$error} List:{$list_id}", 'WARNING');
            return false;
        } else {
            return true;
        }
    }

    /**
     * Batch subscribe via API - hit from MailchimpSubscribeJob
     * Retrieve all subscribers marked new and attempt to subscribe them
     */
    public static function batch_subscribe($limit = 100, $report_only = false): array|false
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
        } catch (Exception $exception) {
            Logger::log("FAIL: could not batch_subscribe, error=" . $exception->getMessage(), 'NOTICE');
        }

        return false;
    }

    #[\Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_VIEW');
    }

    #[\Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_EDIT');
    }

    /**
     * Only admin can delete subscribers
     */
    #[\Override]
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'ADMIN');
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'MAILCHIMP_SUBSCRIBER_CREATE');
    }

    #[\Override]
    public function providePermissions()
    {
        return [
            'MAILCHIMP_SUBSCRIBER_VIEW' => [
                'name' => _t(self::class . '.MAILCHIMP_SUBSCRIBER_VIEW', 'View Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_SUBSCRIBER_EDIT' => [
                'name' => _t(self::class . '.MAILCHIMP_SUBSCRIBER_EDIT', 'Edit Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ],
            'MAILCHIMP_SUBSCRIBER_CREATE' => [
                'name' => _t(self::class . '.MAILCHIMP_SUBSCRIBER_CREATE', 'Create Mailchimp Subscribers'),
                'category' => 'Mailchimp',
            ]
        ];
    }
}
