<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Fixed on purpose: account balances, the cash report and the ledger audit key off these values.
 */
enum InstrumentMovementType: string
{
    use HasLabel;

    case Deposit = 'deposit';
    case Collect = 'collect';
    case Endorse = 'endorse';
    case Bounce = 'bounce';
    case Pay = 'pay';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Tahsile verildi',
            self::Collect => 'Tahsil edildi',
            self::Endorse => 'Ciro edildi',
            self::Bounce => 'Karşılıksız',
            self::Pay => 'Ödendi',
        };
    }

    /**
     * The button that records this movement.
     */
    public function action(): string
    {
        return match ($this) {
            self::Deposit => 'Tahsile ver',
            self::Collect => 'Tahsil et',
            self::Endorse => 'Ciro et',
            self::Bounce => 'Karşılıksız',
            self::Pay => 'Ödendi',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Deposit => 'fa-solid fa-building-columns',
            self::Collect => 'fa-solid fa-hand-holding-dollar',
            self::Endorse => 'fa-solid fa-share',
            self::Bounce => 'fa-solid fa-ban',
            self::Pay => 'fa-solid fa-money-check-dollar',
        };
    }

    public function resultingStatus(): InstrumentStatus
    {
        return match ($this) {
            self::Deposit => InstrumentStatus::InCollection,
            self::Collect => InstrumentStatus::Collected,
            self::Endorse => InstrumentStatus::Endorsed,
            self::Bounce => InstrumentStatus::Bounced,
            self::Pay => InstrumentStatus::Paid,
        };
    }

    /**
     * Account kinds the movement goes through; empty when it names no account.
     * Collecting and paying move money in or out of that account; a deposit only says which bank holds the cheque.
     *
     * @return list<AccountType>
     */
    public function accountTypes(): array
    {
        return match ($this) {
            self::Deposit => [AccountType::Bank],
            self::Collect, self::Pay => [AccountType::Cash, AccountType::Bank],
            default => [],
        };
    }

    /**
     * What the movement does, in the words of the action form.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Deposit => 'Çek tahsil için bankaya verilir. Para henüz hesaba geçmez, cari etkilenmez.',
            self::Collect => 'Para seçilen hesaba girer. Cari etkilenmez; borç çek alındığında kapanmıştı.',
            self::Endorse => 'Çek başka bir cariye ödeme olarak verilir. O cari, tutar kadar borçlandırılır (ona ödeme yaptık).',
            self::Bounce => 'Ödeme gerçekleşmedi. Çeki/senedi veren ya da alan cari, tutar kadar yeniden borçlu/alacaklı olur.',
            self::Pay => 'Para seçilen hesaptan çıkar. Cari etkilenmez; borç çek verildiğinde kapanmıştı.',
        };
    }
}
