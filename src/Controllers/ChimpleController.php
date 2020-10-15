<?php

namespace NSWDPC\Chimple\Controllers;

use NSWDPC\Chimple\Models\MailchimpConfig;
use NSWDPC\Chimple\Models\MailchimpSubscriber;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
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
    private static $url_segment = 'mc-subscribe/v1';

    private static $hide_generic_form = true;

    private static $disable_security_token = false;

    private static $allowed_actions = [
        'SubscribeForm',
        'subscribe'
    ];

    public function init()
    {
        parent::init();
    }

    public function index()
    {
        $form = $this->SubscribeForm();

        if ($this->request->isAjax()) {
            return $form->addExtraClass('js-ajax-form')->forAjaxTemplate();
        }

        $data = ArrayData::create([
            'IsComplete' => $this->request->getVar('complete'),
            'Code' => $this->Code(),
            'HideGenericChimpleForm' => $this->HideGenericChimpleForm()
        ]);

        return $this->customise($data)->renderWith([ 'ChimpleController', 'Page' ]);
    }

    public function Title()
    {
        return _t(__CLASS__. '.DEFAULT_TITLE', 'Subscribe');
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
     * Return a subscription form if it is enabled
     * @link MailchimpConfig::SubscribeForm
     */
    public function SubscribeForm()
    {
        $enabled = MailchimpConfig::isEnabled();
        if(!$enabled) {
            return null;
        }

        $fields = FieldList::create(
            $name = TextField::create('Name', _t(__CLASS__. '.NAME', 'Name'))
                        ->setAttribute('placeholder', _t(__CLASS__. '.YOUR_NAME', 'Your name'))
                        ->setAttribute('title', _t(__CLASS__. '.NAME', 'Name'))
                        ->setTitle(''),
            $email = EmailField::create('Email', _t(__CLASS__. '.EMAIL', 'Email'))
                        ->setAttribute('placeholder', _t(__CLASS__. '.EMAIL_ADDRESS', 'Email address'))
                        ->setAttribute('title', _t(__CLASS__. '.EMAIL', 'Email'))
                        ->setTitle('')
        );

        $actions = FieldList::create(
            FormAction::create(
                'subscribe',
                _t(__CLASS__ . '.SUBSCRIBE', 'Subscribe')
            )->setUseButtonTag(true)
            ->addExtraClass('signup')
        );

        $form = Form::create($this, 'SubscribeForm', $fields, $actions);
        $form->addExtraClass('subscribe');
        $form->setTemplate('MailchimpSubscriberForm');

        if($this->config()->get('disable_security_token')) {
            $form->disableSecurityToken();
        }
        $form->setFormMethod('POST');

        // allow extensions to manipulate the form
        $form->extend('updateChimpleSubscribeForm');

        return $form;
    }

    /**
     * Subscribe action
     */
    public function subscribe($data = [], Form $form = null)
    {
        $code = strip_tags(trim($data['code'] ?: ''));
        $error_message = "";
        $mc_config = null;

        $enabled = MailchimpConfig::isEnabled();
        if(!$enabled) {
            $error_message = "Subscriptions are not available at the moment";
        }

        // proceed with Email checking...
        if (!$error_message) {

            if (empty($data['Email'])) {
                // fail with error
                $error_message = "No e-mail address was provided";
            }

            if (!Email::is_valid_address($data['Email'])) {
                $error_message = "Please provide a valid e-mail address";
            }
        }


        if (!$error_message) {
            // check code provided
            if (!$code) {
                $error_message = "Sorry, the sign-up could not be completed";
            } else {
                $mc_config = MailchimpConfig::getConfig('', '', $code);
                if (empty($mc_config->ID)) {
                    $error_message = "Sorry, the sign-up could not be completed";
                }

                $list_id = $mc_config->getMailchimpListId();
                if (!$list_id) {
                    $error_message = "Sorry, the sign-up could not be completed";
                }
            }
        }

        // failed prior to subscription
        if ($error_message) {
            if ($this->request->isAjax()) {
                return $this->xhrError(500, $error_message);
            } else {
                $form->sessionError($error_message, ValidationResult::TYPE_ERROR);
                return $this->redirect($this->Link("?complete=n&code={$code}"));
            }
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
                    'Email' => $data['Email'],
                    'SubscribedId' => md5(strtolower($data['Email'])),//may not have the email anymore
                ])->first();

        if (empty($sub->ID)) {
            $sub = MailchimpSubscriber::create();
            $sub->Name = $data['Name'];
            $sub->Email = $data['Email'];
            $sub->MailchimpListId = $list_id;//list they are subscribing to
            $sub->Status = MailchimpSubscriber::CHIMPLE_STATUS_NEW;
            $sub->UpdateExisting = $mc_config->UpdateExisting;
            $sub->SendWelcome = $mc_config->SendWelcome;
            $sub->ReplaceInterests = $mc_config->ReplaceInterests;
            $sub->DoubleOptIn = $mc_config->DoubleOptIn;
            $sub->Tags = $mc_config->Tags;
        }

        $sub_id = $sub->write();

        // handle meta
        if(!empty($data['meta'])) {
            // treat a key marked meta in the form as data for Merge Fields
            $sub->updateMergeFields($data['meta']);
        }

        $success = Config::inst()->get(MailchimpConfig::class, 'success_message');
        $failure = Config::inst()->get(MailchimpConfig::class, 'error_message');
        $complete = 'n';
        if ($this->request->isAjax()) {
            if (!$sub_id) {
                return $this->xhrError(500, $failure);
            } else {
                return $this->xhrSuccess(200, $success);
            }
        } elseif ($sub_id) {
            $complete = 'y';
            $form->sessionMessage($success, ValidationResult::TYPE_GOOD);
        } else {
            $form->sessionError($failure, ValidationResult::TYPE_ERROR);
        }
        return $this->redirect($this->Link("?complete={$complete}&code={$code}"));
    }

    /**
     * Return error response for XHR
     */
    private function xhrError($code = 500, $message = "") {
        $response = new HTTPResponse();
        $response->setStatusCode($code);
        $response->addHeader('Content-Type', 'application/json');
        $response->addHeader('X-Submission-Message', $message);
        $response->output();
    }

    /**
     * Return success response for XHR
     */
    private function xhrSuccess($code = 200, $message = "") {
        $response = new HTTPResponse();
        $response->setStatusCode($code);
        $response->addHeader('Content-Type', 'application/json');
        $response->addHeader('X-Submission-Message', $message);
        $response->output();
    }

}
