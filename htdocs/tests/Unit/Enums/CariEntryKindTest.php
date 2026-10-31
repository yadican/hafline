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

    public function test_every_group_has_a_menu_section_and_sections_partition_groups(): void
    {
        $bySection = [];

        foreach (CariEntryGroup::cases() as $group) {
            $section = $group->menuSection();
            $this->assertContains($section, CariEntryGroup::menuSectionOrder());
            $this->assertNotSame('', $group->menuSectionLabel());
            $bySection[$section][] = $group;
        }

        $this->assertEqualsCanonicalizing(CariEntryGroup::menuSectionOrder(), array_keys($bySection));

        $partitioned = [];

        foreach (CariEntryGroup::menuSectionOrder() as $section) {
            $this->assertArrayHasKey($section, $bySection);
            array_push($partitioned, ...$bySection[$section]);
        }

        $this->assertEqualsCanonicalizing(CariEntryGroup::cases(), $partitioned);
    }
}
