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
    protected function checkPaymentAccount(Validator $validator, array $allowed, string $field = 'bank_id', string $missing = 'Paranın geçtiği hesabı seçin.'): void
    {
        $account = Bank::query()->where('active', true)->find($this->input($field));

        if (! $account) {
            $validator->errors()->add($field, $missing);
        } elseif (! in_array($account->type, $allowed, true)) {
            $validator->errors()->add($field, sprintf(
                'Bu ödeme tipi için %s hesabı seçin.',
                implode(' veya ', array_map(fn (AccountType $type) => mb_strtolower($type->label(), 'UTF-8'), $allowed)),
            ));
        }
    }
}
