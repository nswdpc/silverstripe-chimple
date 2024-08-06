<?php

namespace NSWDPC\Chimple\Forms;

use NSWDPC\Chimple\Services\Logger;
use NSWDPC\Chimple\Traits\SubscriptionForm;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\Validator;

/**
 * Subscription form subclass to handle submissions via XHR
 * Allows overrides of default form behaviour
 */
class XhrSubscribeForm extends SubscribeForm {

    /**
     * Set to true if forms of this class will appear on a publicly cacheable page
     */
    private static bool $disable_security_token = false;

    public function __construct(
        RequestHandler $controller = null,
        $name = self::DEFAULT_NAME,
        FieldList $fields = null,
        FieldList $actions = null,
        Validator $validator = null
    ) {
        parent::__construct($controller, $name, $fields, $actions, $validator);
        if(self::config()->get('disable_security_token')) {
            $this->disableSecurityToken();
        }
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    protected function canBeCached()
    {
        $token = $this->getSecurityToken();
        if ($token && !$token->isEnabled()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @inheritdoc
     * Add attributes
     */
    #[\Override]
    protected function getDefaultAttributes(): array
    {
        $attributes = parent::getDefaultAttributes();
        $attributes['data-xhr'] = 1;
        return $attributes;
    }

}
