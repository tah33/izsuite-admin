<?php

namespace Tests\Concerns;

use App\Mail\StoreCustomerCodeMail;
use Illuminate\Support\Facades\Mail;

/**
 * Storefront customers' accounts (Doc 19 Phase E) on RunsAStorefront's store,
 * for a test that fakes the mail: one signed up and verified, the code last
 * sent to an e-mail, and calls signed in by an account's token - or by none.
 */
trait SignsInStoreCustomers
{
    protected const CUSTOMER_PASSWORD = 'Secret#2026a';

    /** An account for $email, signed up and verified by its code: its sign-in's token. */
    protected function verifiedCustomer(string $email = 'karim@example.test', string $name = 'Karim Rahman'): string
    {
        $this->signUpCustomer($email, $name);

        return $this->asCustomer(null)->postJson(self::STORE.'/account/verify', ['email' => $email, 'code' => $this->codeSentTo($email)])->assertOk()->json('data.token');
    }

    protected function signUpCustomer(string $email = 'karim@example.test', string $name = 'Karim Rahman')
    {
        return $this->asCustomer(null)->postJson(self::STORE.'/account', [
            'name' => $name, 'email' => $email, 'password' => self::CUSTOMER_PASSWORD, 'password_confirmation' => self::CUSTOMER_PASSWORD,
        ])->assertCreated();
    }

    /** The code last e-mailed to $email. */
    protected function codeSentTo(string $email): string
    {
        return Mail::sent(StoreCustomerCodeMail::class, fn (StoreCustomerCodeMail $mail) => $mail->hasTo($email))->last()->code;
    }

    /** The calls that follow, signed in by $token - or by nothing: headers set for earlier calls are let go. */
    protected function asCustomer(?string $token): static
    {
        return $this->flushHeaders()->withHeaders($token ? ['X-Customer-Token' => $token] : []);
    }
}
