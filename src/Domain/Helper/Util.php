<?php

namespace App\Domain\Helper;

use Closure;
use Exception;
use ReflectionException;
use ReflectionProperty;
use ZipArchive;

class Util
{
    public static function getHumanReadableSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 b';
        }
        $unit = array('b','kb','mb','gb','tb','pb');
        $i = (int)floor(log($bytes, 1024));
        return @round($bytes/pow(1024, $i), 2).' '.$unit[$i];
    }

    public static function getHumanReadableDuration(float $milliseconds): string
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);

        $milliseconds = (int)$milliseconds % 1000;
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;
        $hours = $hours % 24;

        $format = '%ums';
        $args[] = $milliseconds;

        if ($seconds > 0) {
            $format = '%us ' . $format;
            $args[] = $seconds;
        }

        if ($minutes > 0) {
            $format = '%um ' . $format;
            $args[] = $minutes;
        }

        if ($hours > 0) {
            $format = '%uh ' . $format;
            $args[] = $hours;
        }

        if ($days > 0) {
            $format = '%ud ' . $format;
            $args[] = $days;
        }

        $args[] = $format;

        return call_user_func_array('sprintf', array_reverse($args));
    }

    public static function getMedian(array $values): ?float
    {
        $values = array_values($values);
        $count = count($values);
        if ($count === 0) {
            return null;
        }
        asort($values);
        $half = (int)floor($count / 2);
        if ($count % 2) {
            return $values[$half];
        }
        return ($values[$half - 1] + $values[$half]) / 2.0;
    }

    public static function hasPrefix($strHaystack, $mixPrefixes): ?string
    {
        // @note (MH) : one or more prefixes
        if (!is_array($mixPrefixes)) {
            $mixPrefixes = array($mixPrefixes);
        }

        foreach ($mixPrefixes as $strPrefix) {
            $strHaystackPrefix = substr($strHaystack, 0, strlen($strPrefix));
            if ($strPrefix === $strHaystackPrefix) {
                return $strPrefix;
            }
        }

        return null;
    }

    /**
     * @param string $strHaystack
     * @param string|array $mixPostfixes
     * @return null|string
     */
    public static function hasPostfix(string $strHaystack, $mixPostfixes): ?string
    {
        if (!is_array($mixPostfixes)) {
            $mixPostfixes = array($mixPostfixes);
        }

        foreach ($mixPostfixes as $strPostfix) {
            $strHaystackPostfix = substr($strHaystack, -strlen($strPostfix));
            if ($strPostfix === $strHaystackPostfix) {
                return $strPostfix;
            }
        }

        return null;
    }

    public static function removeDirectory(string $dir): void
    {
        // Ensure the path is a directory
        if (!is_dir($dir)) {
            return;
        }
        // Get the list of files and subdirectories in the directory
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $currentPath = $dir . DIRECTORY_SEPARATOR . $file;

            // Recursively remove files and subdirectories
            if (is_dir($currentPath)) {
                self::removeDirectory($currentPath);
            } else {
                // Unlink (delete) the file
                unlink($currentPath);
            }
        }

        // Remove the directory itself
        rmdir($dir);
    }

    /**
     * @throws Exception
     */
    public static function createZipFromFolder(string $zipFilepath, string $folderPath): void
    {
        if (!is_dir($folderPath)) {
            throw new Exception('The folder "' . $folderPath . '" does not exist.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFilepath, ZipArchive::CREATE) !== true) {
            throw new Exception('Failed to open ZipArchive for writing:' . $zipFilepath);
        }
        self::addFolderToOpenedZipArchive($zip, $folderPath);
        // Close the zip file
        $zip->close();
    }

    /**
     * @throws Exception
     */
    private static function addFolderToOpenedZipArchive(
        ZipArchive $zip,
        string $folderPath,
        string $relativePath = DIRECTORY_SEPARATOR
    ): void {
        $files = array_diff(scandir($folderPath . $relativePath), ['.', '..']);
        foreach ($files as $file) {
            // Calculate the relative path with the basename of the top-level folder
            if (is_dir($folderPath . $relativePath . $file)) {
                // Recursively add subdirectories
                self::addFolderToOpenedZipArchive($zip, $folderPath, $relativePath . $file . DIRECTORY_SEPARATOR);
            } else {
                // Add current file to archive
                $zip->addFile(
                    $folderPath . $relativePath . $file,
                    basename($folderPath) . $relativePath . $file
                );
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    public static function getClassPropertyNames(
        string $className,
        ?int $reflectionPropertyFilter = null,
        ?string $downToBaseClassName = null
    ): array {
        $propertyNames = [];
        self::traverseClassProperties(
            $className,
            function (ReflectionProperty $p) use (&$propertyNames) {
                $propertyNames[] = $p->getName();
            },
            $reflectionPropertyFilter,
            $downToBaseClassName
        );
        return $propertyNames;
    }

    /**
     * @param string $className
     * @param Closure $callback
     * @param int|null $reflectionPropertyFilter bitwise flags of ReflectionProperty constants
     * @param string|null $downToBaseClassName stop traversing, but including this base class
     * @throws ReflectionException
     */
    public static function traverseClassProperties(
        string $className,
        Closure $callback,
        ?int $reflectionPropertyFilter = null,
        ?string $downToBaseClassName = null
    ): void {
        $classes = [$className];
        $currentClass = $className;
        while (false !== $currentClass && $currentClass !== $downToBaseClassName) {
            $currentClass = get_parent_class($currentClass);
            $classes[] = $currentClass;
        }
        foreach ($classes as $currentClass) {
            $currentClassReflection = new \ReflectionClass($currentClass);
            $currentClassProperties = $currentClassReflection->getProperties($reflectionPropertyFilter);
            foreach ($currentClassProperties as $property) {
                if ($property->getDeclaringClass()->getName() !== $currentClass) {
                    continue;
                }
                $callback($property);
            }
        }
    }

    public static function getClassAttribute(\ReflectionClass $class, string $attributeClass): ?object
    {
        if (!class_exists($attributeClass)) {
            throw new \InvalidArgumentException("Attribute class $attributeClass does not exist.");
        }
        $attributes = $class->getAttributes($attributeClass);
        if (empty($attributes)) {
            return null; // No attributes found
        }
        return $attributes[0]->newInstance();
    }

    public static function getPropertyAttribute(\ReflectionProperty $property, string $attributeClass): ?object
    {
        if (!class_exists($attributeClass)) {
            throw new \InvalidArgumentException("Attribute class $attributeClass does not exist.");
        }
        $attributes = $property->getAttributes($attributeClass);
        if (empty($attributes)) {
            return null; // No attributes found
        }
        return $attributes[0]->newInstance();
    }

    public static function getClassUsesRecursive(string|object $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        $results = [];
        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += class_uses($class);
        }
        return array_unique($results);
    }

    public static function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($limit, -1));
        $bytes = (int)$limit;
        switch ($unit) {
            case 'g':
                $bytes *= 1024;
                // fallthrough
            case 'm':
                $bytes *= 1024;
                // fallthrough
            case 'k':
                $bytes *= 1024;
                break;
        }
        return $bytes;
    }
}
