<?php

namespace Tests\Unit\Enums;

use App\Enums\CariEntryGroup;
use App\Enums\CariEntryKind;
use PHPUnit\Framework\TestCase;

class CariEntryKindTest extends TestCase
{
    public function test_every_kind_belongs_to_exactly_one_group_and_groups_partition_kinds(): void
    {
        $assigned = [];

        foreach (CariEntryKind::cases() as $kind) {
            $group = $kind->group();
            $this->assertInstanceOf(CariEntryGroup::class, $group);
            $assigned[] = $kind;
        }

        $this->assertSameSize(CariEntryKind::cases(), array_unique($assigned, SORT_REGULAR));

        $partitioned = [];

        foreach (CariEntryGroup::cases() as $group) {
            $kinds = CariEntryKind::casesIn($group);
            $this->assertNotEmpty($kinds, "Group {$group->value} has no kinds.");

            foreach ($kinds as $kind) {
                $this->assertSame($group, $kind->group());
                $partitioned[] = $kind;
            }
        }

        $this->assertEqualsCanonicalizing(CariEntryKind::cases(), $partitioned);
        $this->assertCount(count(CariEntryKind::cases()), $partitioned);
    }
}
