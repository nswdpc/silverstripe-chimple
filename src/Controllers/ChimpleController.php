<?php

namespace NSWDPC\Chimple\Controllers;

use NSWDPC\Chimple\Forms\SubscribeForm;
use NSWDPC\Chimple\Forms\XhrSubscribeForm;
use NSWDPC\Chimple\Exceptions\RequestException;
use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use NSWDPC\Chimple\Services\Logger;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Validator;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ValidationResult;
use PageController;

/**
 * Provides a controller that can be used to subscribe names/addresses to forms
 */
class ChimpleController extends PageController
{

    /**
     * @var string
     */
    private static $url_segment = 'mc-subscribe/v1';

    /**
     * @var bool
     */
    private static $hide_generic_form = true;

    /**
     * @var array
     */
    private static $allowed_actions = [
        'SubscribeForm',
        'XhrSubscribeForm'
    ];

    protected $formNameSuffix = "";

    public function index()
    {
        // work out if complete or not
        $is_complete = $this->request->getVar('complete');
        $data = ArrayData::create([
            'IsComplete' => $is_complete,
            'Code' => $this->Code(),
            'HideGenericChimpleForm' => $this->HideGenericChimpleForm(),
            'Title' => $this->pageTitle($is_complete)
        ]);
        return $this->customise($data)->renderWith([ 'ChimpleController', 'Page' ]);
    }

    public function pageTitle($complete = null)
    {
        switch($complete) {
            case 'y':
                return _t(__CLASS__. '.DEFAULT_TITLE_SUCCESSFUL', 'Thanks for subscribing');
                break;
            case 'n':
                return _t(__CLASS__. '.DEFAULT_TITLE_NOT_SUCCESSFUL', 'Sorry, there was an error');
                break;
            default:
                return _t(__CLASS__. '.DEFAULT_TITLE', 'Subscribe');
                break;
        }
    }

    public function Link($action = null)
    {
        return Director::absoluteURL(
            Controller::join_links(
                $this->config()->url_segment,
                $action
            )
        );
    }

    public function Code()
    {
        return $this->request->getVar('code');
    }

    public function HideGenericChimpleForm()
    {
        return $this->config()->get('hide_generic_form');
    }

    /**
     * This returns false to avoid the form being included in generic $Form templates/layouts
     * Use $ChimpleSubscribeForm('some-code') in templates instead
     */
    public function Form()
    {
        return false;
    }

    /**
     * Set a suffix form the form name
     */
    public function setFormNameSuffix(string $suffix = '') : self {
        $suffix = trim($suffix);
        $this->formNameSuffix = $suffix;
        return $this;
    }

    /**
     * Return a suffix to use with the form name
     */
    public function getFormNameSuffix() : string {
        if($this->formNameSuffix) {
            $suffix = "_{$this->formNameSuffix}";
        } else {
            $suffix = "";
        }
        return $suffix;
    }

    /**
     * Get a subscription form based on parameters
     */
    public function getSubscriptionForm($useXhr = false) : ?SubscribeForm {
        if($useXhr) {
            $form = $this->XhrSubscribeForm();
        } else {
            $form = $this->SubscribeForm();
        }
        return $form;
    }

    /**
     * Return a subscription form if it is enabled
     * @link MailchimpConfig::SubscribeForm
     */
    public function XhrSubscribeForm() : XhrSubscribeForm
    {

        $enabled = MailchimpConfig::isEnabled();
        if(!$enabled) {
            return null;
        }

        $form = XhrSubscribeForm::create(
            $this,
            "SubscribeForm{$this->getFormNameSuffix()}",
            $this->getFields(),
            $this->getActions(),
            $this->getValidator()
        );

        $form = $this->configureForm($form);
        $form->setFormAction( $this->Link('XhrSubscribeForm') );

        // Set a validation response callback handling for XHR form submissions
        $form->setValidationResponseCallback($this->getCallbackForXhrValidation());

        // this form doesn't need to retain state
        $form->clearMessage();

        return $form;
    }

    /**
     * Return a subscription form if it is enabled
     * @link MailchimpConfig::SubscribeForm
     */
    public function SubscribeForm()
    {

        $enabled = MailchimpConfig::isEnabled();
        if(!$enabled) {
            return null;
        }

        $form = SubscribeForm::create(
            $this,
            "SubscribeForm{$this->getFormNameSuffix()}",
            $this->getFields(),
            $this->getActions(),
            $this->getValidator()
        );

        $form = $this->configureForm($form);
        $form->setFormAction( $this->Link('SubscribeForm') );

        // Handle error validation with custom callback
        $form->setValidationResponseCallback($this->getCallbackForValidation($form));

        return $form;
    }

    /**
     * Apply common configuration to a subscription form
     */
    protected function configureForm(SubscribeForm $form) : SubscribeForm {
        // Form JS, incl XHR handling
        Requirements::javascript(
            'nswdpc/silverstripe-chimple:client/static/js/chimple.js'
        );

        $form->addExtraClass('subscribe chimple');
        $form->setTemplate('MailchimpSubscriberForm');

        if($form->hasMethod('enableSpamProtection')) {
            $form->enableSpamProtection();
        }

        // allow extensions to manipulate the form
        $form->extend('updateChimpleSubscribeForm');

        return $form;
    }

    /**
     * Get fields for the form
     */
    protected function getFields() : FieldList {
        $fields = FieldList::create(
            $name = TextField::create('Name', _t(__CLASS__. '.NAME', 'Name'))
                        ->setAttribute('placeholder', _t(__CLASS__. '.YOUR_NAME', 'Your name'))
                        ->setAttribute('title', _t(__CLASS__. '.NAME', 'Name'))
                        ->setAttribute('required', 'required'),
            $email = EmailField::create('Email', _t(__CLASS__. '.EMAIL', 'Email'))
                        ->setAttribute('placeholder', _t(__CLASS__. '.EMAIL_ADDRESS', 'Email address'))
                        ->setAttribute('title', _t(__CLASS__. '.EMAIL', 'Email'))
                        ->setAttribute('required', 'required')
        );
        return $fields;
    }

    /**
     * Get actions for the form
     */
    protected function getActions() : FieldList {
        $actions = FieldList::create(
            FormAction::create(
                'subscribe',
                _t(__CLASS__ . '.SUBSCRIBE', 'Subscribe')
            )->setUseButtonTag(true)
            ->addExtraClass('signup')
        );
        return $actions;
    }

    /**
     * Return the default validator for the form.
     * @returns Validator|null
     */
    protected function getValidator() : ?Validator {
        return RequiredFields::create(['Name','Email']);
    }

    /**
     * Returns the validation callback upon errors
     * A response is returned only upon errors in XHR submissions
     * See FormRequestHandler::getValidationErrorResponse();
     * @return callable
     */
    protected function getCallbackForXhrValidation() : callable {
        return function(ValidationResult $result) {
            // Fail, using the first message returned from the validation result
            $messages = $result->getMessages();
            $message = (!empty($messages[0]['message']) ? $messages[0]['message'] : '');
            return $this->xhrError(400, $message);
        };
    }

    /**
     * Callback validator for SubscribeForm, avoid redirectBack()
     */
    protected function getCallbackForValidation(SubscribeForm $form) : callable {
        return function(ValidationResult $result) use ($form) {
            // Prior to redirection, persist this result in session to re-display on redirect
            $form->setSessionValidationResult($result);
            $form->setSessionData($form->getData());
            // Create a redirect response for incomplete form
            $query = [
                'complete' => 'n'
            ];
            $query_string = http_build_query($query);
            return $this->redirect($this->Link("?" . $query_string));
        };
    }

    /**
     * Handle errors, based on the request type
     */
    private function handleError($code, $error_message, Form $form = null) {
        if($this->request->isAjax()) {
            return $this->xhrError($code, $error_message);
        } else if($form) {
            // set session error on the form
            $form->sessionError($error_message, ValidationResult::TYPE_ERROR);
        }
        return;
    }

    /**
     * Handle successful submissions, based on the request type
     */
    private function handleSuccess($code, $message, Form $form = null) {
        $success_message = Config::inst()->get(MailchimpConfig::class, 'success_message');
        if($this->request->isAjax()) {
            return $this->xhrSuccess($code, $message, $success_message);
        } else if($form) {
            // set session message on the form
            $form->sessionMessage($success_message, ValidationResult::TYPE_GOOD);
        }
        return;
    }

    /**
     * Subscribe action
     */
    public function subscribe($data = [], Form $form = null)
    {

        try {

            $response = null;
            $code = "";// MailchimpConfig.Code
            $list_id = "";

            if(!$form) {
                throw new RequestException("Forbidden", 403);
            }

            $mc_config = null;

            if(empty($data['code'])) {
                // fail with error
                $error_message = _t(
                    __CLASS__ . '.NO_CODE',
                    "No code was provided"
                );
                $error_code = 400;// default to invalid data

            } else {

                $code = strip_tags(trim($data['code'] ?: ''));
                $error_message = "";
                $error_code = 400;// default to invalid data
                $mc_config = null;

            }

            $enabled = MailchimpConfig::isEnabled();
            if(!$enabled) {
                $error_message = _t(
                    __CLASS__ . '.SUBSCRIPTIONS_NOT_AVAILABLE',
                    "Subscriptions are not available at the moment"
                );
            }

            // proceed with Email checking...
            if (!$error_message) {
                if (empty($data['Email'])) {
                    // fail with error
                    $error_message = _t(
                        __CLASS__ . '.NO_EMAIL_ADDRESS',
                        "No e-mail address was provided"
                    );
                }
                if (!Email::is_valid_address($data['Email'])) {
                    $error_message = _t(
                        __CLASS__ . '.INVALID_EMAIL_ADDRESS',
                        "Please provide a valid e-mail address, '{email}' is not valid",
                        [
                            'email' => htmlspecialchars($data['Email'])
                        ]
                    );
                }
            }

            if (!$error_message) {
                // check code provided
                if (!$code) {
                    $error_message = _t(
                        __CLASS__ . ".GENERIC_ERROR_1",
                        "Sorry, the sign-up could not be completed"
                    );
                } else {
                    $mc_config = MailchimpConfig::getConfig('', '', $code);
                    if (empty($mc_config->ID)) {
                        $error_message = _t(
                            __CLASS__ . ".GENERIC_ERROR_2",
                            "Sorry, the sign-up could not be completed"
                        );
                    }
                    $list_id = $mc_config->getMailchimpListId();
                }
            }

            if (!$list_id) {
                $error_message = _t(
                    __CLASS__ . ".GENERIC_ERROR_3",
                    "Sorry, the sign-up could not be completed"
                );
            }

            // failed prior to subscription
            if ($error_message) {
                throw new RequestException($error_message, $error_code);
            }

            $sub_id = false;
            // see if email is assigned to list already
            $sub = MailchimpSubscriber::get()
                    // for this list
                    ->filter([
                        'MailchimpListId' => $list_id
                    ])
                    // for the Email or the MD5 of it
                    ->filterAny([
                        'Email' => $data['Email'],// match on email address provided
                        'SubscribedId' => MailchimpSubscriber::getMailchimpSubscribedId($data['Email'])// OR may not have the email anymore
                    ])->first();

            if (empty($sub->ID)) {
                $sub = MailchimpSubscriber::create();
                $sub->Name = $data['Name'];
                $sub->Email = $data['Email'];
                $sub->MailchimpListId = $list_id;//list they are subscribing to
                $sub->Status = MailchimpSubscriber::CHIMPLE_STATUS_NEW;
                $sub->Tags = $mc_config->Tags;
                $sub_id = $sub->write();
                if (!$sub_id) {
                    throw new RequestException("502", "Bad Gateway");
                }
            }

            // handle meta
            if(!empty($data['meta'])) {
                // treat a key marked meta in the form as data for Merge Fields
                $sub->updateMergeFields($data['meta']);
            }

            // handle a successful subscription
            $response = $this->handleSuccess(200, "OK", $form);
            if($response && ($response instanceof HTTPResponse)) {
                // handle responses for e.g XHR
                return $response;
            } else {
                // Create a redirect response for success
                $query = [
                    'complete' => 'y'
                ];
                $query_string = http_build_query($query);
                return $this->redirect($this->Link("?" . $query_string));
            }

        } catch (RequestException $e) {
            $error_message = $e->getMessage();
            $error_code = $e->getCode();
        } catch (\Exception $e) {
            // general exceptin
            $error_message = Config::inst()->get(MailchimpConfig::class, 'error_message');
            $error_code = 500;
        }

        // Handle subscribe attempt failures
        $response = $this->handleError($error_code, $error_message, $form);
        if($response && ($response instanceof HTTPResponse)) {
            // handle XHR error responses
            return $response;
        } else {
            // Create a redirect response for errors
            $query = [
                'complete' => 'n'
            ];
            $query_string = http_build_query($query);
            return $this->redirect($this->Link("?" . $query_string));
        }

    }

    /**
     * Return error response for XHR
     * @return HTTPResponse
     */
    private function xhrError($code = 500, $message = "", $description = "") {
        $response = new HTTPResponse();
        $response->setStatusCode($code);
        $response->addHeader('Content-Type', 'application/json');
        $response->addHeader('X-Submission-OK', 0);
        $response->addHeader('X-Submission-Description', $message);
        return $response;
    }

    /**
     * Return success response for XHR
     * @return HTTPResponse
     */
    private function xhrSuccess($code = 200, $message = "", $description = "") {
        $response = new HTTPResponse();
        $response->setStatusCode($code);
        $response->addHeader('Content-Type', 'application/json');
        $response->addHeader('X-Submission-OK', 1);
        $response->addHeader('X-Submission-Description', $description);
        return $response;
    }

}
