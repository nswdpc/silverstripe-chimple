<?php

namespace NSWDPC\Chimple\Controllers;

use NSWDPC\Chimple\Models\MailchimpSubscriber;
use NSWDPC\Chimple\Models\MailchimpConfig;
use SilverStripe\Admin\ModelAdmin;

/**
 * MailchimpAdmin
 *
 * @author james.ellis@dpc.nsw.gov.au
 */
class MailchimpAdmin extends ModelAdmin
{
    private static $managed_models = [
        MailchimpSubscriber::class,
        MailchimpConfig::class
    ];

    private static $menu_title = 'Mailchimp';

    private static $url_segment = 'mailchimp';
}
