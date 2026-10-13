<?php

namespace Database\Seeders;

use App\Models\Example;
use Illuminate\Database\Seeder;

class ExampleSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['aktif', 'taslak', 'kapali'];
        $shifts = ['gunduz', 'gece'];
        $titles = ['34 HL 1042'];

        for ($number = 1001; count($titles) < 15; $number++) {
            $titles[] = '34 HL '.$number;
        }

        Example::query()->whereNotIn('title', $titles)->delete();

        foreach ($titles as $index => $title) {
            $record = Example::query()->updateOrCreate(
                ['title' => $title],
                [
                    'note' => $title === '34 HL 1042'
                        ? 'Kadıköy şantiyesinden sahte sefer notu. Form elemanlarının dolu hali.'
                        : 'Sahte liste kaydı '.($index + 1),
                    'email' => 'sofor'.($index + 1).'@hafline.test',
                    'phone' => '0532'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                    'website' => 'https://hafline.test',
                    'reference' => 'SF-'.(1042 - $index),
                    'quantity' => $index + 1,
                    'amount' => 1000 + ($index * 75),
                    'event_date' => '2026-10-14',
                    'event_time' => sprintf('%02d:30:00', 8 + ($index % 8)),
                    'starts_at' => '2026-10-14 '.sprintf('%02d:30:00', 8 + ($index % 8)),
                    'event_month' => '2026-10',
                    'event_week' => '2026-W42',
                    'color' => '#c4892e',
                    'level' => 20 + ($index * 5),
                    'status' => $statuses[$index % 3],
                    'shift' => $shifts[$index % 2],
                    'tags' => ['sefer'],
                    'channels' => ['panel'],
                    'active' => $index % 4 !== 3,
                    'source' => 'panel',
                ],
            );

            $record->created_at = now()->subMinutes($index);
            $record->save();
        }
    }
}
