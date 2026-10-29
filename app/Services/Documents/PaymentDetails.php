<?php

namespace App\Services\Documents;

use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use App\Repositories\User\PaymentAccountRepository;
use App\Support\BankLabels;
use App\Support\Countries;

/**
 * What customer documents print of the business's payment accounts (Doc 05
 * section 9): the active accounts in the document's currency set to show on
 * documents - of its location or the whole business - each with its public
 * details only. Balances, internal notes, a gateway's merchant reference and
 * settlement currency never print.
 *
 * Each account comes as its name, its details as label and value - a bank's
 * labels as its country names them - and its payment instructions: ready to
 * keep in a document's snapshot, so a later change never reaches a document
 * already numbered.
 */
class PaymentDetails
{
    public function __construct(
        protected PaymentAccountRepository $paymentAccountRepository,
    ) {}

    /** @return list<array{name: string, details: list<array{0: string, 1: string}>, instructions: ?string}> */
    public function for(Workspace $workspace, string $currency, ?int $locationId): array
    {
        return $this->paymentAccountRepository->forDocuments($workspace, $currency, $locationId)
            ->map(fn (PaymentAccount $account) => [
                'name'         => $account->name,
                'details'      => $this->details($account),
                'instructions' => $account->payment_instructions,
            ])
            ->values()
            ->all();
    }

    /** One account as a block of text: its name, a line for each detail, then its instructions. */
    public function text(array $account): string
    {
        return implode("\n", array_filter([
            $account['name'],
            ...array_map(fn (array $detail) => "{$detail[0]}: {$detail[1]}", $account['details']),
            $account['instructions'],
        ]));
    }

    /** One account on one line, for a statement's notes. */
    public function line(array $account): string
    {
        $details = implode(', ', array_map(fn (array $detail) => "{$detail[0]} {$detail[1]}", $account['details']));

        return 'Pay to '.$account['name'].($details ? " - {$details}" : '').($account['instructions'] ? ". {$account['instructions']}" : '');
    }

    /** @return list<array{0: string, 1: string}> */
    private function details(PaymentAccount $account): array
    {
        $labels = BankLabels::for($account->bank_country);

        $rows = match ($account->type) {
            'bank' => [
                ['Bank', $account->bank_name],
                ['Account Holder', $account->holder_name],
                [$labels['number'], $account->account_number],
                ['Branch', $account->branch],
                [$labels['code'], $account->swift_code],
                ['Bank Country', Countries::name($account->bank_country)],
            ],
            'mobile_money' => [
                ['Provider', $account->bank_name],
                ['Registered Name', $account->holder_name],
                ['Wallet Number', $account->account_number],
                ['Merchant or Till Number', $account->merchant_number],
            ],
            'payment_gateway' => [
                ['Pay Through', $account->bank_name],
                ['Merchant', $account->merchant_name],
            ],
            'other' => [
                ['Provider', $account->bank_name],
                ['Account Holder', $account->holder_name],
                ['Reference', $account->account_number],
            ],
            // Cash: its name and instructions say where to pay.
            default => [],
        };

        return array_values(array_filter($rows, fn (array $row) => filled($row[1])));
    }
}
