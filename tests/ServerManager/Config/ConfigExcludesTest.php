<?php

namespace App\Tests\ServerManager\Config;

use App\Domain\Config\ConfigExcludes;
use PHPUnit\Framework\TestCase;

/**
 * What is left out when a config folder is read: names (a folder or a file anywhere) and paths (from the root).
 */
class ConfigExcludesTest extends TestCase
{
    public function testWithoutPatternsNothingIsLeftOut(): void
    {
        $excludes = new ConfigExcludes([]);

        $this->assertTrue($excludes->isEmpty());
        $this->assertFalse($excludes->excludes('NotMaintained/a.json'));
        $this->assertTrue(new ConfigExcludes(['', ' ', '/', '//'])->isEmpty(), 'empty patterns say nothing');
    }

    public function testANameLeavesOutAFolderAnywhereWithEverythingInIt(): void
    {
        $excludes = new ConfigExcludes(['NotMaintained']);

        $this->assertTrue($excludes->excludes('NotMaintained/a.json'));
        $this->assertTrue($excludes->excludes('x/NotMaintained/a.json'));
        $this->assertTrue($excludes->excludes('x/NotMaintained/y/z.json'));
        $this->assertFalse($excludes->excludes('NotMaintainedMore/a.json'), 'a name is the whole name of a folder');
        $this->assertFalse($excludes->excludes('Maint/a.json'));
        $this->assertFalse($excludes->excludes('x/a.json'));
    }

    public function testANameLeavesOutAFileWithOrWithoutJson(): void
    {
        $this->assertTrue(new ConfigExcludes(['CS_Basic'])->excludes('a/CS_Basic.json'));
        $this->assertTrue(new ConfigExcludes(['CS_Basic.json'])->excludes('a/CS_Basic.json'));
        $this->assertFalse(new ConfigExcludes(['CS_Basic'])->excludes('a/CS_Basic2.json'));
        $this->assertTrue(new ConfigExcludes(['CS_Basic'])->excludes('CS_Basic/other.json'), 'a folder name too');
    }

    public function testWildcardsInANameStayInOneName(): void
    {
        $this->assertTrue(new ConfigExcludes(['*_old.json'])->excludes('NS/NS_old.json'));
        $this->assertFalse(new ConfigExcludes(['*_old.json'])->excludes('NS/NS_old2.json'));
        $this->assertTrue(new ConfigExcludes(['NS_?'])->excludes('x/NS_1.json'));
        $this->assertFalse(new ConfigExcludes(['NS_?'])->excludes('x/NS_12.json'));
        $this->assertTrue(new ConfigExcludes(['Old*'])->excludes('Older/a.json'));
    }

    public function testAPathIsFromTheRootAndLeavesOutWhatIsBelowIt(): void
    {
        $excludes = new ConfigExcludes(['NS/old']);

        $this->assertTrue($excludes->excludes('NS/old/a.json'));
        $this->assertTrue($excludes->excludes('NS/old/x/y.json'));
        $this->assertFalse($excludes->excludes('x/NS/old/a.json'), 'from the root: not anywhere');
        $this->assertFalse($excludes->excludes('NS/older/a.json'));
        $this->assertFalse($excludes->excludes('NS/a.json'));
    }

    public function testAStarInAPathStaysInOneFolderAndTwoStarsDoNot(): void
    {
        $this->assertTrue(new ConfigExcludes(['NS/*_copy.json'])->excludes('NS/a_copy.json'));
        $this->assertFalse(new ConfigExcludes(['NS/*_copy.json'])->excludes('NS/x/a_copy.json'));
        $this->assertTrue(new ConfigExcludes(['NS/*'])->excludes('NS/a.json'));
        $this->assertTrue(new ConfigExcludes(['NS/*'])->excludes('NS/x/y.json'), 'a matching folder takes its files');
        $this->assertTrue(new ConfigExcludes(['NS/**'])->excludes('NS/x/y/z.json'));
        $this->assertTrue(new ConfigExcludes(['**/x'])->excludes('x/a.json'), '**/ can be no folder at all');
        $this->assertTrue(new ConfigExcludes(['**/x'])->excludes('a/b/x/c.json'));
        $this->assertFalse(new ConfigExcludes(['**/x'])->excludes('a/b/xx/c.json'));
    }

    public function testAPathAlsoMatchesWithoutJson(): void
    {
        $this->assertTrue(new ConfigExcludes(['NotMaintained/CS_Basic'])->excludes('NotMaintained/CS_Basic.json'));
        $this->assertFalse(new ConfigExcludes(['NotMaintained/CS_Basic'])->excludes('NotMaintained/CS_Other.json'));
    }

    public function testBackslashesAndSlashesAroundAPatternAreNotMeaningful(): void
    {
        $this->assertTrue(new ConfigExcludes(['NS\\old'])->excludes('NS/old/a.json'));
        $this->assertTrue(new ConfigExcludes(['/NotMaintained/'])->excludes('NotMaintained/a.json'));
        $this->assertTrue(new ConfigExcludes(['NotMaintained'])->excludes('NotMaintained\\a.json'), 'a Windows path');
    }

    public function testCharactersThatAreNotWildcardsAreLiteral(): void
    {
        $this->assertTrue(new ConfigExcludes(['a.b'])->excludes('a.b/x.json'));
        $this->assertFalse(new ConfigExcludes(['a.b'])->excludes('axb/x.json'));
        $this->assertTrue(new ConfigExcludes(['[x](1)+'])->excludes('[x](1)+/a.json'));
        $this->assertFalse(new ConfigExcludes(['[x]'])->excludes('x/a.json'));
    }

    public function testSeveralPatternsAreAnyOfThem(): void
    {
        $excludes = new ConfigExcludes(['NotMaintained', 'NS/old', '*_copy.json']);

        $this->assertTrue($excludes->excludes('NotMaintained/a.json'));
        $this->assertTrue($excludes->excludes('NS/old/a.json'));
        $this->assertTrue($excludes->excludes('x/a_copy.json'));
        $this->assertFalse($excludes->excludes('NS/a.json'));
    }

    public function testTheCaseOfLettersMattersOnlyWhereTheFileSystemDoesNotCare(): void
    {
        $leftOut = new ConfigExcludes(['NotMaintained'])->excludes('notmaintained/a.json');

        $this->assertSame(PHP_OS_FAMILY === 'Windows', $leftOut);
    }
}
