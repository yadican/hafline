<?php

namespace Database\Seeders\LoadTest;

use App\Enums\AccountType;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentMovementType;
use App\Enums\InstrumentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\FinancialInstrument;
use App\Models\Party;
use App\Models\User;
use App\Services\FinancialInstrumentService;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Settles cheques and notes that CariStage parked in the portfolio / issued tray.
 *
 * 43a — received: most matured ones are collected, some endorsed to a payee, a few bounced;
 * papers still due stay open for the dashboard reminders.
 * 43b — given: matured issued papers are paid out of a cash/bank account (bankFor).
 */
final class InstrumentStage
{
    public function __construct(
        private Progress $progress,
        private FinancialInstrumentService $instruments,
    ) {}

    public function run(CatalogStage $catalog, Carbon $to): void
    {
        $this->progress->stage('Çek / senet (alınanlar)');

        $received = $this->settleReceived($catalog, $to);

        $this->progress->done(sprintf(
            'tahsil %d · ciro %d · karşılıksız %d · portföyde kalan %d',
            $received['collected'],
            $received['endorsed'],
            $received['bounced'],
            $received['portfolio'],
        ));

        $this->progress->stage('Çek / senet (verilenler)');

        $given = $this->settleGiven($catalog, $to);

        $this->progress->done(sprintf(
            'ödendi %d · ödenecek kalan %d',
            $given['paid'],
            $given['issued'],
        ));
    }

    /**
     * Walk received portfolio papers and apply collect / endorse / bounce on matured ones.
     *
     * @return array{collected: int, endorsed: int, bounced: int, portfolio: int}
     */
    public function settleReceived(CatalogStage $catalog, Carbon $to): array
    {
        $userId = User::query()->value('id');
        $banks = $catalog->banksOf(AccountType::Bank);
        $cash = $catalog->banksOf(AccountType::Cash);
        $collectAccounts = $banks->isNotEmpty() ? $banks : $cash;
        $payees = $this->endorsePayees($catalog);

        $open = FinancialInstrument::query()
            ->where('direction', InstrumentDirection::Received)
            ->where('status', InstrumentStatus::Portfolio)
            ->orderBy('issued_on')
            ->orderBy('number')
            ->get();

        $matured = $open->filter(fn (FinancialInstrument $paper) => $paper->due_on->lte($to))->values();
        $future = $open->filter(fn (FinancialInstrument $paper) => $paper->due_on->gt($to))->values();

        $counts = [
            'collected' => 0,
            'endorsed' => 0,
            'bounced' => 0,
            'portfolio' => $future->count(),
        ];

        if ($matured->isEmpty() || $collectAccounts->isEmpty()) {
            $counts['portfolio'] += $matured->count();

            return $counts;
        }

        $bounceTarget = max(2, (int) round($matured->count() * 0.08));
        $endorseTarget = (int) round($matured->count() * 0.25);
        $leaveOverdue = (int) round($matured->count() * 0.12);

        foreach ($matured->values() as $index => $paper) {
            if ($index < $bounceTarget) {
                $this->instruments->record($paper, [
                    'type' => InstrumentMovementType::Bounce->value,
                    'occurred_on' => $this->stepOn($paper->issued_on, $paper->due_on, $to),
                    'description' => 'Karşılıksız (seed)',
                ], $userId);
                $counts['bounced']++;

                continue;
            }

            if ($index < $bounceTarget + $endorseTarget) {
                $payee = $payees->first(fn (Party $party) => $party->id !== $paper->party_id);

                if ($payee !== null) {
                    $this->instruments->record($paper, [
                        'type' => InstrumentMovementType::Endorse->value,
                        'occurred_on' => $this->stepOn(
                            $paper->issued_on,
                            $paper->issued_on->copy()->addDays(fake()->numberBetween(3, 20)),
                            $to,
                        ),
                        'party_id' => $payee->id,
                        'description' => 'Ciro (seed)',
                    ], $userId);
                    $counts['endorsed']++;

                    continue;
                }
            }

            if ($leaveOverdue > 0 && $index >= $matured->count() - $leaveOverdue) {
                $counts['portfolio']++;

                continue;
            }

            $account = $collectAccounts->random();
            $on = $this->stepOn($paper->issued_on, $paper->due_on, $to);

            if (fake()->boolean(20) && $banks->isNotEmpty()) {
                $depositBank = $banks->random();
                $depositOn = $this->stepOn(
                    $paper->issued_on,
                    $paper->issued_on->copy()->addDays(fake()->numberBetween(1, 10)),
                    Carbon::parse($on),
                );
                $this->instruments->record($paper, [
                    'type' => InstrumentMovementType::Deposit->value,
                    'occurred_on' => $depositOn,
                    'bank_id' => $depositBank->id,
                    'description' => 'Tahsile verildi (seed)',
                ], $userId);
                $paper->refresh();
            }

            $this->instruments->record($paper, [
                'type' => InstrumentMovementType::Collect->value,
                'occurred_on' => $on,
                'bank_id' => $account->id,
                'description' => 'Tahsil (seed)',
            ], $userId);
            $counts['collected']++;
        }

        return $counts;
    }

    /**
     * Walk issued (given) papers and pay matured ones from a cash/bank account.
     *
     * @return array{paid: int, issued: int}
     */
    public function settleGiven(CatalogStage $catalog, Carbon $to): array
    {
        $userId = User::query()->value('id');

        $open = FinancialInstrument::query()
            ->where('direction', InstrumentDirection::Given)
            ->where('status', InstrumentStatus::Issued)
            ->orderBy('issued_on')
            ->orderBy('number')
            ->get();

        $matured = $open->filter(fn (FinancialInstrument $paper) => $paper->due_on->lte($to))->values();
        $future = $open->filter(fn (FinancialInstrument $paper) => $paper->due_on->gt($to))->values();

        $counts = [
            'paid' => 0,
            'issued' => $future->count(),
        ];

        if ($matured->isEmpty()) {
            return $counts;
        }

        foreach ($matured as $paper) {
            $account = $this->payAccount($catalog);

            if ($account === null) {
                $counts['issued']++;

                continue;
            }

            $this->instruments->record($paper, [
                'type' => InstrumentMovementType::Pay->value,
                'occurred_on' => $this->stepOn($paper->issued_on, $paper->due_on, $to),
                'bank_id' => $account->id,
                'description' => 'Ödeme (seed)',
            ], $userId);
            $counts['paid']++;
        }

        return $counts;
    }

    /**
     * Cash or bank account for a Pay step — same bankFor pools payment methods use.
     */
    private function payAccount(CatalogStage $catalog): ?Bank
    {
        $preferBank = fake()->boolean(70);

        return $preferBank
            ? ($catalog->bankFor(PaymentMethod::BankTransfer) ?? $catalog->bankFor(PaymentMethod::Cash))
            : ($catalog->bankFor(PaymentMethod::Cash) ?? $catalog->bankFor(PaymentMethod::BankTransfer));
    }

    /**
     * @return Collection<int, Party>
     */
    private function endorsePayees(CatalogStage $catalog): Collection
    {
        return collect([
            ...$catalog->partiesFor(PartyRole::Supplier),
            ...$catalog->partiesFor(PartyRole::Fuel),
            ...$catalog->partiesFor(PartyRole::DumpOperator),
        ])->unique('id')->values();
    }

    /**
     * Movement day: not before the paper arrived, not after the seed horizon, preferring $want.
     */
    private function stepOn(Carbon $issuedOn, Carbon $want, Carbon $cap): string
    {
        $day = $want->copy()->startOfDay();

        if ($day->lt($issuedOn->copy()->startOfDay())) {
            $day = $issuedOn->copy()->startOfDay();
        }

        if ($day->gt($cap->copy()->startOfDay())) {
            $day = $cap->copy()->startOfDay();
        }

        return $day->toDateString();
    }
}
