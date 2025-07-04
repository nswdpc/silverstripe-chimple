<?php

namespace NSWDPC\Chimple\Tests;

use DrewM\MailChimp\MailChimp;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use SilverStripe\Dev\TestOnly;

/**
 * Simple log handling
 */
class TestMailchimpApiClient extends MailChimp implements TestOnly
{
    /**
     * By default, subscribers do not exist, flip to true in test to test update handling
     */
    protected static bool $subscriber_exists = false;

    /**
     * An array of subscriber data, relating the subscriber record that is current
     */
    protected static array $subscriber = [];

    public static string $last_mock_error = '';

    public static array $last_mock_response = [];

    public static function setSubscriberExists(bool $exists): void
    {
        static::$subscriber_exists = $exists;
    }

    public static function setSubscriber(array $subscriber = []): void
    {
        static::$subscriber = $subscriber;
    }

    /**
     * Override last error handling, to never return an error
     */
    #[\Override]
    public function getLastError()
    {
        return self::$last_mock_error !== '' ? self::$last_mock_error : false;
    }

    /**
     * Override last error handling, to never return an error
     */
    #[\Override]
    public function getLastResponse()
    {
        return self::$last_mock_response;
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function delete($method, $args = [], $timeout = self::TIMEOUT)
    {
        return $this->makeTestRequest('delete', $method, $args, $timeout);
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function get($method, $args = [], $timeout = self::TIMEOUT)
    {
        return $this->makeTestRequest('get', $method, $args, $timeout);
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function patch($method, $args = [], $timeout = self::TIMEOUT)
    {
        return $this->makeTestRequest('patch', $method, $args, $timeout);
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function post($method, $args = [], $timeout = self::TIMEOUT)
    {
        return $this->makeTestRequest('post', $method, $args, $timeout);
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function put($method, $args = [], $timeout = self::TIMEOUT)
    {
        return $this->makeTestRequest('put', $method, $args, $timeout);
    }

    /**
     * Simulates a request to the endpoint
     */
    private function makeTestRequest(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): mixed
    {
        self::$last_mock_error = '';
        self::$last_mock_response = [];

        $testMethod = $this->determineApiAction($http_verb, $method);
        if (method_exists($this, $testMethod)) {
            $response = $this->{$testMethod}($http_verb, $method, $args, $timeout);
            if (is_array($response)) {
                self::$last_mock_response = $response;
            }

            return $response;
        }

        throw new \InvalidArgumentException("Unsupported test method: {$testMethod}");
    }

    /**
     * Work out the method to call based on the HTTP verb and method string
     */
    protected function determineApiAction(string $http_verb, string $method): string
    {
        switch ($http_verb) {
            case 'get':
                if (preg_match("|^lists/[^/]+/members/[^/]+$|", $method)) {
                    return "getSubscriber";
                }

                if (preg_match("|^lists/[^/]+/members/[^/]+/tags/.+|", $method)) {
                    return "getSubscriberTags";
                }
                // no break
            case 'post':
                if (preg_match("|^lists/[^/]+/members/[^/]+/tags$|", $method)) {
                    return "postSubscriberTags";
                }

                if (preg_match("|^lists/[^/]+/members$|", $method)) {
                    return "createSubscriber";
                }
                // no break
            case 'put':
                // handle used put requests
                break;
            case 'patch':
                if (preg_match("|^lists/[^/]+/members/.+$|", $method)) {
                    return "updateSubscriber";
                }
        }

        throw new \InvalidArgumentException("Unsupported {$http_verb}:{$method}");
    }

    /**
     * Get a subscriber
     */
    protected function getSubscriber(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): ?array
    {
        if (!self::$subscriber_exists) {
            return null;
        } else {

            $tags = [];
            if (isset(self::$subscriber['tags']) && is_array(self::$subscriber['tags'])) {
                foreach (self::$subscriber['tags'] as $i => $tag) {
                    $tags[] = [
                        "id" => ($i + 1),
                        "name" => $tag
                    ];
                }
            }

            $response = [
                'id' =>  parent::subscriberHash(self::$subscriber['email']),
                'email' => self::$subscriber['email'],
                'email_type' => MailchimpSubscriber::MAILCHIMP_EMAIL_TYPE_HTML,
                'status' => 'pending',
                'unique_email_id' => bin2hex(random_bytes(16)),
                'web_id' => bin2hex(random_bytes(8)),
                'full_name' => trim(self::$subscriber['fname'] . " " . self::$subscriber['lname']),
                'merge_fields' => [
                    'FNAME' => trim((string) self::$subscriber['fname']),
                    'LNAME' => trim((string) self::$subscriber['lname'])
                ]
            ];
            $response['tags'] = $tags;
            return $response;
        }
    }

    /**
     * Returns the state of the current set of tags for a user
     */
    protected function getSubscriberTags(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): array
    {
        $tags = [];
        if (isset(self::$subscriber['tags']) && is_array(self::$subscriber['tags'])) {
            foreach (self::$subscriber['tags'] as $i => $tag) {
                $tags[] = [
                    "id" => ($i + 1),
                    "name" => $tag
                ];
            }
        }

        return [
            'tags' => $tags,
            'total_items' => count($tags)
        ];
    }

    /**
     * Method returns an empty response (204)
     */
    protected function postSubscriberTags(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): array
    {
        return [];
    }

    /**
     * Return mock response for creating a new subscriber
     */
    protected function createSubscriber(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): array
    {
        $tags = [];
        if (isset($args['tags']) && is_array($args['tags'])) {
            foreach ($args['tags'] as $i => $tag) {
                $tags[] = [
                    "id" => ($i + 1),
                    "name" => $tag
                ];
            }
        }

        $response = [
            'id' =>  MailchimpSubscriber::getMailchimpSubscribedId($args['email_address']),
            'email' => $args['email_address'],
            'email_type' => MailchimpSubscriber::MAILCHIMP_EMAIL_TYPE_HTML,
            'status' => $args['status'],
            'unique_email_id' => bin2hex(random_bytes(16)),
            'web_id' => bin2hex(random_bytes(8)),
            'full_name' => trim(($args['merge_fields']['FNAME'] ?? '') . " " . ($args['merge_fields']['LNAME'] ?? '')),
            'merge_fields' => [
                'FNAME' => trim($args['merge_fields']['FNAME'] ?? ''),
                'LNAME' => trim($args['merge_fields']['LNAME'] ?? '')
            ]
        ];
        $response['tags'] = $tags;
        return $response;
    }

    /**
     * Return mock response when an existing subscriber  is updated
     */
    protected function updateSubscriber(string $http_verb, string $method, array $args = [], int $timeout = parent::TIMEOUT): array
    {
        $response = [
            'id' =>  MailchimpSubscriber::getMailchimpSubscribedId($args['email_address']),
            'email' => $args['email_address'],
            'email_type' => MailchimpSubscriber::MAILCHIMP_EMAIL_TYPE_HTML,
            'status' => $args['status'],
            'unique_email_id' => bin2hex(random_bytes(16)),
            'web_id' => bin2hex(random_bytes(8)),
            'full_name' => trim(($args['merge_fields']['FNAME'] ?? '') . " " . ($args['merge_fields']['LNAME'] ?? '')),
            'merge_fields' => [
                'FNAME' => trim($args['merge_fields']['FNAME'] ?? ''),
                'LNAME' => trim($args['merge_fields']['LNAME'] ?? '')
            ]
        ];

        $tags = [];
        if (isset(self::$subscriber['tags']) && is_array(self::$subscriber['tags'])) {
            foreach (self::$subscriber['tags'] as $i => $tag) {
                $tags[] = [
                    "id" => ($i + 1),
                    "name" => $tag
                ];
            }
        }

        if (isset(self::$subscriber['tags_for_update']) && is_array(self::$subscriber['tags_for_update'])) {
            foreach (self::$subscriber['tags_for_update'] as $i => $tag) {
                $tags[] = [
                    "id" => ($i + 1),
                    "name" => $tag
                ];
            }
        }

        $response['tags'] = $tags;
        return $response;
    }
}
