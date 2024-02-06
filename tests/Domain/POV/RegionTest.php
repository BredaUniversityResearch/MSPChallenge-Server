<?php
// tests/Domain/POV/RegionTest.php

namespace App\Tests\Domain\POV;

use PHPUnit\Framework\TestCase;
use App\Domain\POV\Region;

class RegionTest extends TestCase
{
    public function testCreateClampedBy(): void
    {
        // Test case 1
        $clamp = new Region(2, 0, 5, 3);
        $region = new Region(3, 1, 4, 2);
        $result = $region->createClampedBy($clamp);
        $this->assertEquals([3, 1, 4, 2], array_values($result->toArray()));

        // Test case 2
        $region = new Region(4, -2, 6, 2);
        $result = $region->createClampedBy($clamp);
        $this->assertEquals([4, 0, 5, 2], array_values($result->toArray()));

        // Test case 3
        $region = new Region(-1, 2, 6, 8);
        $result = $region->createClampedBy($clamp);
        $this->assertEquals([2, 2, 5, 3], array_values($result->toArray()));

        // Test case 4
        $region = new Region(0, 0, 1, 1);
        $result = $region->createClampedBy($clamp);
        $this->assertNull($result);

        // Test case 5
        $region = new Region(6, -1, 6, 4);
        $result = $region->createClampedBy($clamp);
        $this->assertNull($result);
    }
}
