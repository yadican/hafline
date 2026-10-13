<?php

namespace App\Models;

use Database\Factories\ExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'note',
    'email',
    'phone',
    'website',
    'secret',
    'reference',
    'quantity',
    'amount',
    'event_date',
    'event_time',
    'starts_at',
    'event_month',
    'event_week',
    'color',
    'level',
    'status',
    'shift',
    'tags',
    'channels',
    'active',
    'attachment',
    'source',
])]
class Example extends Model
{
    /** @use HasFactory<ExampleFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'decimal:2',
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'level' => 'integer',
            'tags' => 'array',
            'channels' => 'array',
            'active' => 'boolean',
        ];
    }
}
