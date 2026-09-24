<?php

namespace App\Domains\RAG\Attributes;

use LogicException;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Discovers driver classes by scanning a directory for a driver attribute.
 */
final class DriverDiscovery
{
    /**
     * Find every instantiable class under $directory that implements $contract
     * and carries $attribute, keyed by the attribute's driver name.
     *
     * @param string $directory Directory to scan
     * @param string $namespace PSR-4 namespace that maps to $directory
     * @param class-string<DriverAttribute> $attribute
     * @param class-string $contract
     * @return array<string, class-string>
     * @throws LogicException If two classes claim the same driver name
     */
    public static function discover(string $directory, string $namespace, string $attribute, string $contract): array
    {
        $drivers = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
            $class = rtrim($namespace, '\\') . '\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            if (!class_exists($class) || !is_subclass_of($class, $contract)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                continue;
            }

            foreach ($reflection->getAttributes($attribute) as $reflectionAttribute) {
                $name = $reflectionAttribute->newInstance()->name;

                if (isset($drivers[$name])) {
                    throw new LogicException(
                        "Driver [{$name}] is declared by both {$drivers[$name]} and {$class}."
                    );
                }

                $drivers[$name] = $class;
            }
        }

        ksort($drivers);

        return $drivers;
    }
}
