# Documentation

## Contents

+ Configuration defaults in the administration area
+ The subscription process
+ Submitting without a redirect
+ Using multiple forms for the same Mailchimp audience ID
+ [Developer integration](./002_integration.md)
+ [Elemental support](./003_elemental.md)

## Configuration defaults

If you have access to the `Mailchimp Configuration` administration area you may add, remove and update configurations.
Each configuration can be used to allow site visitors to subscribe to a Mailchimp list with various default options based on the values you provide.

+ Title: this value is used in the administration area only
+ Submit without redirecting (see below)
+ Code: a unique code used to represent this record. It can be used by a developer to include a subscription form in a page statically (eg. a universal footer or header form)
+ Heading: this will be displayed at the top of the form, if provided
+ Mailchimp list id: the value retrieved from the "Audience name and defaults" page in the Mailchimp administration area. It could be noted as the "Audience ID" or "List ID" and looks something like `abc45de6`
+ Content to show before form: HTML content of your choosing. This can be overridden in the Elemental element
+ Content to show after form: HTML content of your choosing. This can be overridden in the Elemental element
+ Tags assigned to the subscriber: assign default tags when a subscriber successfully subscribes via this form. This can assist with analytics to determine which forms perform the best or worst.

## The subscription process

When a user successfully submits the subscription form, they will enter a queue awaiting submission to Mailchimp. The frequency of the submission is determined by the queue configuration. By default it runs every 300 seconds (5 minutes).

Each subscription record will have one of the following values:
+ New: the subscription attempt has not yet been made
+ Processing: the record is in the process of being subscribed
+ Success: Mailchimp accepted the subscription creation/update request
+ Fail: Mailchimp denied the subscription creation/update request (see Last error value for possible reasons)

All subscribers are created as "pending" by default. They will receive a double opt-in email for Mailchimp to confirm that they actually were the website visitor entering their own email address.

Once the visitor confirms their subscription, they will appear in the Mailchimp audience list on your Mailchimp website control panel. In your website their name and email address will be obfuscated by default eg. `T•••y`

If a visitor resubscribes to the same list, the values they provide will update their Mailchimp audience values for that list. This includes adding new default tags.

If a subscription fails, this will be noted in the Mailchimp subscriber administration area of your website.

## Cleanup

Subscription attempts are removed from your website with the following schedule:

+ 30 minutes for successful subscriptions
+ 7 days for failed subscriptions. This allows you to retry subscriptions as required.

To retry a failed subscription, set their "Status" to "New" and it will be picked up on the next queue run. Note that it may fail again.

## Submitting without a redirect

## For content authors and administrators

If you have access to the Mailchimp Configuration admninistration area, checking the `Submit without redirecting` checkbox will cause the form for that configuration to submit without a redirect.

This means that the person subscribing will see the subscription result on the same page as the form they have just submitted.

<img src="../img/config_usexhr.png">

<hr>

If you are a content author, you can select a Mailchimp Configuration and optionally override its setting using the content element's `Submit without redirecting` option

<img src="../img/element_usexhr.png">

### For developers

By default, the global form will submit in place via an AJAX (XHR) submission.

The global form is one added to your template via the `$ChimpleGlobalSubscribeForm` template variable. It will use the `use_xhr` value specified in config.yml

```yaml
NSWDPC\Chimple\Models\MailchimpConfig:
  use_xhr: true|false|null
```

+ `true`: submit using XHR
+ `false`: submit with a redirect
+ `null`: let the checkbox value for the Default configuration record in the administration area decide

Note that using XHR submissions will assist in maintaining cacheable cache-control headers.

## Adding multiple forms for the same audience

You can add multiple forms for the same Mailchimp audience by creating one configuration for each copy you need. Set the `Code` value for each copy to be unique and represent what the form does or the context that it has.

Each configuration will be given a random code on save, if none is provided.

The `Code` value is used in HTML so putting identifiable text in it is not advised.
