<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Party;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests für die Party Entity
 */
class PartyTest extends TestCase
{
    // ========== isSingleParent() ==========

    public function testIsSingleParentWithOneParent(): void
    {
        $party = new Party();
        $party->setParentNames(['Anna Schmidt']);

        $this->assertTrue($party->isSingleParent());
    }

    public function testIsSingleParentWithTwoParents(): void
    {
        $party = new Party();
        $party->setParentNames(['Maria Müller', 'Thomas Müller']);

        $this->assertFalse($party->isSingleParent());
    }

    public function testIsSingleParentWithEmptyArray(): void
    {
        $party = new Party();
        $party->setParentNames([]);

        $this->assertFalse($party->isSingleParent());
    }

    // ========== getGeneratedPassword() ==========

    public function testGetGeneratedPasswordSingleChild(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'Max', 'birthYear' => 2019]]);

        $this->assertSame('M2019', $party->getGeneratedPassword());
    }

    public function testGetGeneratedPasswordUsesOldestChild(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'Sophie', 'birthYear' => 2020],
            ['name' => 'Max', 'birthYear' => 2018],
            ['name' => 'Emma', 'birthYear' => 2021],
        ]);

        // Ältestes Kind: Max (2018)
        $this->assertSame('M2018', $party->getGeneratedPassword());
    }

    public function testGetGeneratedPasswordUppercasesFirstLetter(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'leon', 'birthYear' => 2019]]);

        $this->assertSame('L2019', $party->getGeneratedPassword());
    }

    public function testGetGeneratedPasswordNoChildren(): void
    {
        $party = new Party();
        $party->setChildren([]);

        $this->assertSame('', $party->getGeneratedPassword());
    }

    // ========== getOldestChild() ==========

    public function testGetOldestChildSingle(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'Max', 'birthYear' => 2020]]);

        $oldest = $party->getOldestChild();
        $this->assertSame('Max', $oldest['name']);
        $this->assertSame(2020, $oldest['birthYear']);
    }

    public function testGetOldestChildMultiple(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'Sophie', 'birthYear' => 2021],
            ['name' => 'Max', 'birthYear' => 2018],
            ['name' => 'Leon', 'birthYear' => 2020],
        ]);

        $oldest = $party->getOldestChild();
        $this->assertSame('Max', $oldest['name']);
        $this->assertSame(2018, $oldest['birthYear']);
    }

    public function testGetOldestChildEmpty(): void
    {
        $party = new Party();
        $party->setChildren([]);

        $this->assertNull($party->getOldestChild());
    }

    // ========== getChildrenNames() ==========

    public function testGetChildrenNamesSingle(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'Max', 'birthYear' => 2020]]);

        $this->assertSame('Max', $party->getChildrenNames());
    }

    public function testGetChildrenNamesMultiple(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'Max', 'birthYear' => 2020],
            ['name' => 'Sophie', 'birthYear' => 2021],
        ]);

        $this->assertSame('Max, Sophie', $party->getChildrenNames());
    }

    public function testGetChildrenNamesEmpty(): void
    {
        $party = new Party();
        $party->setChildren([]);

        $this->assertSame('', $party->getChildrenNames());
    }

    // ========== hasChildBornIn() ==========

    public function testHasChildBornInTrue(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'Max', 'birthYear' => 2019],
            ['name' => 'Sophie', 'birthYear' => 2021],
        ]);

        $this->assertTrue($party->hasChildBornIn(2019));
        $this->assertTrue($party->hasChildBornIn(2021));
    }

    public function testHasChildBornInFalse(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'Max', 'birthYear' => 2019]]);

        $this->assertFalse($party->hasChildBornIn(2020));
    }

    // ========== addChild() / removeChild() ==========

    public function testAddChild(): void
    {
        $party = new Party();
        $party->addChild('Max', 2019);
        $party->addChild('Sophie', 2021);

        $children = $party->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame('Max', $children[0]['name']);
        $this->assertSame('Sophie', $children[1]['name']);
    }

    public function testRemoveChild(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'Max', 'birthYear' => 2019],
            ['name' => 'Sophie', 'birthYear' => 2021],
        ]);

        $party->removeChild(0);

        $children = $party->getChildren();
        $this->assertCount(1, $children);
        $this->assertSame('Sophie', $children[0]['name']);
    }

    public function testRemoveChildReindexes(): void
    {
        $party = new Party();
        $party->setChildren([
            ['name' => 'A', 'birthYear' => 2019],
            ['name' => 'B', 'birthYear' => 2020],
            ['name' => 'C', 'birthYear' => 2021],
        ]);

        $party->removeChild(1); // Entferne 'B'

        $children = $party->getChildren();
        $this->assertCount(2, $children);
        $this->assertSame('A', $children[0]['name']);
        $this->assertSame('C', $children[1]['name']);
    }

    // ========== __toString() ==========

    public function testToString(): void
    {
        $party = new Party();
        $party->setChildren([['name' => 'Max', 'birthYear' => 2019]]);
        $party->setParentNames(['Maria Müller', 'Thomas Müller']);

        $this->assertSame('Max (Maria Müller, Thomas Müller)', (string) $party);
    }

    // ========== Konstruktor ==========

    public function testConstructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $party = new Party();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($party->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $party->getCreatedAt());
        $this->assertLessThanOrEqual($after, $party->getCreatedAt());
    }
}
