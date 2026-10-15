<?php

namespace App\Services\Ledger;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One posting a source document wants on a party's account.
 */
final readonly class LedgerLine
{
    public string $debit;

    public string $credit;

    public string $occurredOn;

    public function __construct(
        public string $partyId,
        CarbonInterface $occurredOn,
        string|float $debit,
        string|float $credit,
        public ?string $description = null,
        public ?string $vehicleId = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $cashCategoryId = null,
    ) {
        $this->occurredOn = $occurredOn->toDateString();
        $this->debit = self::money($debit);
        $this->credit = self::money($credit);
    }

    public static function debit(string $partyId, CarbonInterface $occurredOn, string|float $amount, ?string $description = null, ?string $vehicleId = null): self
    {
        return new self($partyId, $occurredOn, $amount, 0, $description, $vehicleId);
    }

    public static function credit(string $partyId, CarbonInterface $occurredOn, string|float $amount, ?string $description = null, ?string $vehicleId = null): self
    {
        return new self($partyId, $occurredOn, 0, $amount, $description, $vehicleId);
    }

    public static function money(string|float|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Marks the line as a payment so it shows up in per-method reports (cash, cheque, ...).
     */
    public function paidBy(?PaymentMethod $method, ?string $cashCategoryId = null): self
    {
        return new self(
            $this->partyId,
            Carbon::parse($this->occurredOn),
            $this->debit,
            $this->credit,
            $this->description,
            $this->vehicleId,
            $method,
            $cashCategoryId,
        );
    }

    public function isZero(): bool
    {
        return (float) $this->debit === 0.0 && (float) $this->credit === 0.0;
    }

    /**
     * What decides whether a posted line still matches; descriptions are informational only.
     */
    public function key(): string
    {
        return implode('|', [
            $this->partyId,
            $this->occurredOn,
            $this->debit,
            $this->credit,
            $this->vehicleId,
            $this->paymentMethod?->value,
            $this->cashCategoryId,
        ]);
    }
}
