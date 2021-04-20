# Integration

This page contains further developer integration documentation.

## Templating

### The global form

To include a subscribe form using the default configuration:

Use the include provided by the module
```ss
<% include ChimpleGlobalSubscribeForm %>
```
OR, call the global template variable in a template
```ss
$ChimpleGlobalSubscribeForm
```

### For a specific form configuration

To include a subscriber form using the a specific configuration record, specify the `MailchimpConfiguration.Code` value:

Use the include provided by the module
```ss
<% include ChimpleSubscribeForm Code='footer-form' %>
```
OR
```ss
$ChimpleSubscribeForm('footer-form')
```

(This requires a Mailchimp Configuration record with a code of 'footer-form' to be present)

### Specifying XHR submission in the template

The administration area allows users with permissions to set whether a form should submit via XHR (submit without redirecting). This is useful when forms are used in a publicly cached page.

The behaviour for a form can be set in code via a template using a 2nd argument to `$ChimpleSubscribeForm`

> Turning this on/off via a template will override whatever configuration setting is set in the administration area for the relevant subscription form.

####  Allow the configuration record to decide:
```ss
$ChimpleSubscribeForm('footer-form')
```

#### Force XHR off
```ss
$ChimpleSubscribeForm('footer-form', 0)
```

#### Force XHR on
```ss
$ChimpleSubscribeForm('footer-form', 1)
```

## Good-to-know

### Submission endpoint

Form data will post to the module's ChimpleController endpoint

### DOM id clashes

If you want to add the same subscription form multiple times in a page, add multiple configurations for the same List (audience) ID and have one form/element per configuration.

### HTML templating

The module provides basic HTML and no CSS by default:

+ Content before the form, if it exists
+ An image if it exists
+ The form
+ Content after the form, if it exists

Further integration into your project is up to you or your developer.
