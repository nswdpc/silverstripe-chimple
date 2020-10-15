# Codeception testing notes

## Acceptance test requirements

Acceptance tests require ChromeDriver to be installed on the testing environment.

1. For Ubuntu, install the package ```chromium-chromedriver``` or get ChromeDriver for your OS from https://sites.google.com/a/chromium.org/chromedriver/downloads (you can use other browser drivers if you like)
1. ```$ which chromedriver``` should return something like ```/usr/bin/chromedriver```
1. If you install the ```chromium-chromedriver``` package, it will install ```chromium-browser```. Chromium browser should be found at ```/usr/bin/chromium-browser```

## Checks

Run a few checks to see things are installed

```bash
chromium-browser --version
Chromium 85.0.4183.83 snap

chromedriver --version
ChromeDriver 85.0.4183.83 (94abc2237ae0c9a4cb5f035431c8adfb94324633-refs/branch-heads/4183@{#1658})
```

(Chromium might be installed as a snap package in more recent Ubuntus)

## Setup

> You should have a Mailchimp account with an audience configured for tests and an API key labelled for tests only, so it can be toggled to inactive.
>
> Ideally, the account used for testing should be a standalone test account (e.g a Free account).

### Test Environment

Ensure you have the correct testing environment set up

```yaml
---
Name: silverstripe-chimple-testing
After:
  - '#silverstripe-chimple'
Only:
  environment: 'dev'
---
# test config
NSWDPC\Chimple\MailchimpConfig:
  api_key: '<api_key>'
  # the audience ID
  list_id: '<list_id>'
# subscriber info
NSWDPC\Chimple\ChimpleSubscriberTest:
  test_email_domain: '<an email domain you can receive email at>'
  test_email_user: '<a user you can receive email at the domain>'
  # adds a +test<random> to the end of the user part of the email address
  test_use_plus: true
```

## Running

For Acceptance tests, ensure ChromeDriver is started:

```bash
chromedriver --url-base=/wd/hub
Starting ChromeDriver 85.0.4183.83 (94abc2237ae0c9a4cb5f035431c8adfb94324633-refs/branch-heads/4183@{#1658}) on port 9515
Only local connections are allowed.
Please see https://chromedriver.chromium.org/security-considerations for suggestions on keeping ChromeDriver safe.
ChromeDriver was started successfully.
```

Your project level codeception.dist.yml file should have an include pointing at the tests directory, something like:

```yml
include:
  - vendor/nswdpc/silverstripe-chimple/tests/codeception
paths:
  log: /path/to/codeception-logs
```

# Running the test

Run codeception from the **project** directory
```
./vendor/bin/codecept run
```

## Output

Codeception outputs debug data like screenshots and failure output to `./tests/_output`

Have a look there to determine what might be going wrong if/when tests fail

## Troubleshooting

+ You get the error `Can't connect to Webdriver at ....` - have you started ChromeDriver ?
+ You get the error `unknown error: no chrome binary at /path/to/chromium-browser` - was Chromium installed and available at the path shown?
+ You get various codeception errors about `codeception/module-****` - make sure these are installed
+ You get DB connection errors - is the DB accessible and are the credentials correct? Check the host is accessible.
+ You get certificate errors - use localhost as the host name or use http:// to help chromedrive work around that
