# Chimple - Simple Mailchimp handling in Silverstripe

This module allows you to manage list subscriptions from a silverstripe app/website.

It provides:

+ a subscription controller
+ multiple configuration records for setting multiple list/audience configurations
+ a default configuration record assigned in Settings/SiteConfig
+ configurable defaults in yaml
+ queued jobs to handle subscription and failure checking
+ a Mailchimp model admin to view subscriptions and subscription results
+ an elemental subscription form element
+ Merge Fields support
+ Tags for subscribers
+ ability to submit a form with or without (via XHR) redirecting.

### Setup

+ Configure your Mailchimp API key and list (audience)
+ Set up one or more configurations, assign one as the default
+ Ensure queues are running
+ Test a subscription to your lists

[Further documentation beyond the basics is available](./docs/en/001_index.md)

### Templating

To include a subscribe form using the default configuration:

Use the include provided by the module
```
<% include ChimpleGlobalSubscribeForm %>
```
OR, call the global template variable in a template
```
$ChimpleGlobalSubscribeForm
```

To include a subscriber form using the a specific configuration record, specify the `MailchimpConfiguration.Code` value:

Use the include provided by the module
```
<% include ChimpleSubscribeForm Code='footer-form' %>
```
OR
```
$ChimpleSubscribeForm('footer-form')
```

### Specifying XHR submission in the template

The administration area allows users with permissions to set whether a form should submit via XHR. This can be modified in the template using a 2nd argument:


#### Allow the configuration record to decide:
```
$ChimpleGlobalSubscribeForm('footer-form')
```

#### Force XHR off
```
$ChimpleGlobalSubscribeForm('footer-form', 0)
```

#### Force XHR on
```
$ChimpleGlobalSubscribeForm('footer-form')
```


### Submission endpoint

Forms will post to the module's controller endpoint

### DOM id clashes

If, for some reason you want to add the same subscription for multiple times in a page, add multiple configurations for the same List (audience) ID and have one form/element per configuration.

### CSS and JS

The module provides basic HTML and no CSS by default, further integration into your project is up to you or your developer.

### Content element

A subscription element for use with the Elemental module is provided. Content authors can add a subscription form to a page and configure:

+ submitting with/without redirect
+ the audience ID value
+ before/after HTML content to render with the form

## Requirements

See [composer.json](./composer.json)

## Installation

```
composer require nswdpc/silverstripe-chimple
```

## License

BSD-3-Clause

See [License](./LICENSE.md)

## Configuration

Example project configuration for your project

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

## Maintainers

NSW DPC Digital

## Credits

This module is a combination of work contributed to various projects over the years by the NSW DPC Digital team and Symbiote.

## Bugtracker

Please raise bugs (with instructions on how to reproduce), questions and feature requests on the Github bug tracker

## Development and contribution

If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers.
