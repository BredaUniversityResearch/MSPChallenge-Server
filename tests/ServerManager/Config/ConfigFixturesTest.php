<?php

namespace App\Tests\ServerManager\Config;

use Symfony\Component\Filesystem\Filesystem;

class ConfigFixturesTest extends ConfigCommandTestCase
{
    public function testTheFixturesAreTheSixOriginalConfigs(): void
    {
        $files = self::realConfigFiles();

        $this->assertSame(array_keys(ConfigFixtures::CHECKSUMS), array_keys($files));
        $this->assertCount(6, $files);
        foreach ($files as $id => $contents) {
            $this->assertSame(ConfigFixtures::CHECKSUMS[$id], hash('sha256', $contents), $id);
        }
        $this->assertSame(array_keys($files), array_keys(self::realConfigs()));
    }

    public function testTheOriginalsAreCompleteConfigsNotStrippedOnes(): void
    {
        foreach (self::realConfigs() as $id => $config) {
            $this->assertFalse(\App\Domain\Config\Merge\RegionConfigMerger::isRegionFormat($config), $id);
            $this->assertNotNull($config->datamodel->CEL ?? null, "$id still has its top-level CEL");
        }
    }

    public function testADirectoryWithTheSameFilesIsAccepted(): void
    {
        $this->writeOriginals();

        $this->assertSameFiles(self::realConfigFiles(), ConfigFixtures::fromDirectory($this->dir));
    }

    public function testAFileThatIsNotTheOriginalIsRejected(): void
    {
        $this->writeOriginals();
        $id = array_key_first(ConfigFixtures::CHECKSUMS);
        file_put_contents($this->dir . '/' . $id . '.json', self::realConfigFiles()[$id] . ' ');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("$id.json is not the file of revision d6e5d75 (checksum mismatch)");
        ConfigFixtures::fromDirectory($this->dir);
    }

    public function testAMissingFileIsReported(): void
    {
        $this->writeOriginals();
        $id = array_key_last(ConfigFixtures::CHECKSUMS);
        new Filesystem()->remove($this->dir . '/' . $id . '.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Missing {$this->dir}/$id.json");
        ConfigFixtures::fromDirectory($this->dir);
    }

    public function testTheDownloadIsCachedAsAZipWithTheSameBytes(): void
    {
        if (getenv(ConfigFixtures::ENV_DIRECTORY)) {
            $this->markTestSkipped(ConfigFixtures::ENV_DIRECTORY . ' is set: nothing is downloaded or cached.');
        }
        $files = self::realConfigFiles();
        $cache = ConfigFixtures::cachePath(self::projectDir());

        $this->assertFileExists($cache);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($cache));
        $this->assertSame(count($files), $zip->numFiles);
        foreach ($files as $id => $contents) {
            $this->assertSameContents($contents, $zip->getFromName($id . '.json'), $id);
        }
        $zip->close();
    }
}
