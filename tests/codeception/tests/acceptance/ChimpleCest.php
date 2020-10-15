<?php

namespace NSWDPC\Chimple\Tests;

use NSWDPC\Chimple\AcceptanceTester;

class ChimpleCest
{
    /**
     * Adds subscriber to a form and checks for successful response
     */
    public function subscribeTest(AcceptanceTester $I)
    {

        $date = date('YmdHis');
        $name = "Acceptance Tester";
        $email = "test.{$date}@example.com";

        $I->amOnPage("/");

        $I->seeElement(".form-subscribe");

        $code = $I->grabValueFrom('.form-subscribe input[name="code"]');

        $form_id = "form#Form_SubscribeForm_{$code}";
        $I->seeElement($form_id);

        $I->submitForm($form_id, [
            'Name' => $name,
            'Email' => $email
        ]);

        // the URL for completion has these args
        $I->seeInCurrentUrl('complete=y');
        $I->seeInCurrentUrl('code=' . $code);

        // and a new record is in the DB
        $I->seeInDatabase(
            'ChimpleSubscriber',
            [
                'Email' => $email,
                'Name' => 'Acceptance',
                'Surname' => 'Tester',
                'Status' => 'NEW',
            ]
        );

    }
}
