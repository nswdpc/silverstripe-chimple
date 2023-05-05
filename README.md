# Chimple - Simple Mailchimp handling in Silverstripe

This module allows you to create and update Mailchimp list subscriptions via a Silverstripe app/website.

## Developers

It supports:

+ a subscription controller
+ multiple configuration records for setting multiple list/audience combinations
+ multiple forms on the same page, for the same list
+ a spam protection module
+ a default configuration record assigned in Settings/SiteConfig
+ configurable defaults in yaml
+ queued jobs to handle subscription and failure checking
+ a Mailchimp model admin to view current subscriptions and subscription results
+ an [Elemental](https://github.com/silverstripe/silverstripe-elemental) subscription form element - [documentation](./docs/en/003_elemental.md)
+ merge fields support
+ tags for subscribers
+ ability to submit a form with or without (via XHR) redirecting
+ patch previously subscribed list/audience members
+ optionally remove current subscriber tags

It doesn't support:

+ Managing audience members, use the Mailchimp administration area for that.

## Setup

+ Configure your Mailchimp API key and list (audience). You will find this in the Mailchimp account settings and relevant audience, respectively.
+ In your website, set up one or more configurations
+ Assign one of those as the default for the website
+ Ensure queues are running, or get a developer to do this for you.
+ Test a subscription to your lists

[Further documentation beyond the basics is available](./docs/en/001_index.md)

## Configuration

Example project configuration:

```yaml
---
Name: app-mailchimp
After:
  - '#silverstripe-chimple'
---
NSWDPC\Chimple\Models\MailchimpConfig:
  # override the XHR submission setting for the global form only
  use_xhr: true|false
  # account API key
  api_key: '<api key>'
  # default list id
  list_id: '<list id>'
```

[Further integration](./docs/en/002_integration.md)

## Spam protection

Use a [spam protection module](https://github.com/silverstripe/silverstripe-spamprotection) to block spammy submission attempts on the form. The [NSWDPC reCAPTCHAv3 module](https://github.com/nswdpc/silverstripe-recaptcha-v3) is a good option.

If a module is installed, the subscription form will detect this and enable the default spam protector on the form.

## Requirements

See [composer.json](./composer.json)

## Installation

The only supported method of installation is via composer:

```shell
composer require nswdpc/silverstripe-chimple
```

## License

BSD-3-Clause

See [License](./LICENSE.md)

## Maintainers

NSW DPC Digital

## Credits

This module is a combination of work contributed to various projects over the years by the NSW DPC Digital team and Symbiote.

## Bugtracker

Please raise bugs (with instructions on how to reproduce), questions and feature requests on the Github bug tracker

## Security

If you have found a security issue with this module, please email digital[@]dpc.nsw.gov.au in the first instance, detailing your findings.

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
