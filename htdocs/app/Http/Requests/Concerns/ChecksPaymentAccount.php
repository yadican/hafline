<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AccountType;
use App\Models\Bank;
use Illuminate\Validation\Validator;

/**
 * The account a payment names must be an active one of a kind its method moves money through.
 */
trait ChecksPaymentAccount
{
    /**
     * @param  list<AccountType>  $allowed
     */
    protected function checkPaymentAccount(Validator $validator, array $allowed): void
    {
        $account = Bank::query()->where('active', true)->find($this->input('bank_id'));

        if (! $account) {
            $validator->errors()->add('bank_id', 'Paranın geçtiği hesabı seçin.');
        } elseif (! in_array($account->type, $allowed, true)) {
            $validator->errors()->add('bank_id', sprintf(
                'Bu ödeme tipi için %s hesabı seçin.',
                implode(' veya ', array_map(fn (AccountType $type) => mb_strtolower($type->label(), 'UTF-8'), $allowed)),
            ));
        }
    }
}
