<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = ['taslak' => 'draft', 'aktif' => 'active', 'kapali' => 'closed'];
        $shifts = ['gunduz' => 'day', 'gece' => 'night'];
        $tags = ['nakit' => 'cash', 'sefer' => 'trip', 'santiye' => 'site'];
        $channels = ['saha' => 'field', 'rapor' => 'report'];

        DB::table('examples')->orderBy('id')->lazy()->each(function (object $row) use ($statuses, $shifts, $tags, $channels): void {
            DB::table('examples')->where('id', $row->id)->update([
                'status' => $statuses[$row->status] ?? $row->status,
                'shift' => $shifts[$row->shift] ?? $row->shift,
                'tags' => json_encode($this->mapList($row->tags, $tags)),
                'channels' => json_encode($this->mapList($row->channels, $channels)),
            ]);
        });
    }

    public function down(): void
    {
        $statuses = ['draft' => 'taslak', 'active' => 'aktif', 'closed' => 'kapali'];
        $shifts = ['day' => 'gunduz', 'night' => 'gece'];
        $tags = ['cash' => 'nakit', 'trip' => 'sefer', 'site' => 'santiye'];
        $channels = ['field' => 'saha', 'report' => 'rapor'];

        DB::table('examples')->orderBy('id')->lazy()->each(function (object $row) use ($statuses, $shifts, $tags, $channels): void {
            DB::table('examples')->where('id', $row->id)->update([
                'status' => $statuses[$row->status] ?? $row->status,
                'shift' => $shifts[$row->shift] ?? $row->shift,
                'tags' => json_encode($this->mapList($row->tags, $tags)),
                'channels' => json_encode($this->mapList($row->channels, $channels)),
            ]);
        });
    }

    /**
     * @param  array<string, string>  $map
     * @return list<string>
     */
    private function mapList(mixed $value, array $map): array
    {
        $items = is_string($value) ? json_decode($value, true) : $value;
        $items = is_array($items) ? $items : [];

        return array_values(array_map(
            fn (mixed $item): string => $map[(string) $item] ?? (string) $item,
            $items,
        ));
    }
};
