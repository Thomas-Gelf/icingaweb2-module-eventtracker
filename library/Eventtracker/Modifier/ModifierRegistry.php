<?php

namespace Icinga\Module\Eventtracker\Modifier;

use ReflectionClass;
use RuntimeException;

class ModifierRegistry
{
    protected static ?ModifierRegistry $instance = null;
    /** @var array<string, class-string<Modifier>> */
    protected array $modifiers = [];
    protected array $groupedModifiers = [];

    public static function getInstance(): ModifierRegistry
    {
        return self::$instance ??= self::createInstance();
    }

    public static function getClassName(string $shortName): string
    {
        $implementations = static::getInstance()->listModifiers();
        $class = $implementations[$shortName] ?? null;
        if ($class) {
            return $class;
        }

        throw new RuntimeException("There is no such modifier: $shortName");
    }

    protected static function createInstance(): ModifierRegistry
    {
        $self = new ModifierRegistry();
        $prefix = __NAMESPACE__ . '\\';
        foreach (glob(__DIR__ . '/*.php') as $filename) {
            $className = $prefix . substr(basename($filename), 0, -4);
            if (is_a($className, Modifier::class, true)) {
                $classReflection = new ReflectionClass($className);
                if ($classReflection->isInstantiable()) {
                    $self->register($className);
                }
            }
        }

        return $self;
    }

    /**
     * @return array<string, class-string<Modifier>>
     */
    public function listModifiers(): array
    {
        return $this->modifiers;
    }

    /**
     * @param class-string<Modifier> $className
     * @return void
     */
    public function register(string $className)
    {
        $name = $className::getName();
        $this->modifiers[$name] = $className;
    }
}
