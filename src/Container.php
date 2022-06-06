<?php

declare(strict_types=1);

namespace PCore\Di;

use BadMethodCallException;
use Closure;
use PCore\Di\Exceptions\{ContainerException, NotFoundException};
use Psr\Container\{ContainerExceptionInterface, ContainerInterface};
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;
use function array_shift;
use function is_null;
use function is_object;
use function is_string;

/**
 * Class Container
 * @package PCore\Di
 * @github https://github.com/pcore-framework/di
 */
class Container implements ContainerInterface
{

    /**
     * Соответствие между классом и идентичностью
     */
    protected array $bindings = [];

    /**
     * Разрешенный экземпляр
     */
    protected array $resolved = [];

    /**
     * Сохранить созданный класс в массиве
     *
     * @param string $id идентификация, которая может быть интерфейсом
     * @param object $instance пример
     */
    public function set(string $id, object $instance)
    {
        $this->resolved[$this->getBinding($id)] = $instance;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id)
    {
        if ($this->has($id)) {
            return $this->resolved[$this->getBinding($id)];
        }
        throw new NotFoundException('Экземпляр не найден: ' . $id);
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return isset($this->resolved[$this->getBinding($id)]);
    }

    /**
     * @param string $id идентификация, которая может быть интерфейсом
     * @param string $class имя класса
     *
     * @return void
     */
    public function bind(string $id, string $class): void
    {
        $this->bindings[$id] = $class;
    }

    /**
     * @param string $id идентификация, которая может быть интерфейсом
     */
    public function unBind(string $id): void
    {
        if ($this->bound($id)) {
            unset($this->bindings[$id]);
        }
    }

    /**
     * @param string $id идентификация, которая может быть интерфейсом
     * @return bool
     */
    public function bound(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * @param string $id идентификация, которая может быть интерфейсом
     * @return string
     */
    public function getBinding(string $id): string
    {
        return $this->bindings[$id] ?? $id;
    }

    /**
     * Способ ввода внешнего интерфейса
     *
     * @param string $id идентификация, которая может быть интерфейсом
     * @param array $arguments список параметров конструктора
     * @return mixed
     * @throws ReflectionException|NotFoundException|ContainerExceptionInterface
     */
    public function make(string $id, array $arguments = []): object
    {
        if (false === $this->has($id)) {
            $id = $this->getBinding($id);
            $reflectionClass = Reflection::reflectClass($id);
            if ($reflectionClass->isInterface()) {
                if (!$this->bound($id)) {
                    throw new ContainerException($id . ' не имеет класса реализации.', 600);
                }
                $reflectionClass = Reflection::reflectClass($this->getBinding($id));
            }
            $this->set($id, $reflectionClass->newInstanceArgs($this->getConstructorArgs($reflectionClass, $arguments)));
        }
        return $this->get($id);
    }

    /**
     * Удалить из экземпляра
     *
     * @param string $id
     */
    public function remove(string $id): void
    {
        $id = $this->getBinding($id);
        if ($this->has($id)) {
            unset($this->resolved[$id]);
        }
    }

    /**
     * Вызов метода класса
     *
     * @param callable $callable массив вызываемых классов или экземпляров и методов
     * @param array $arguments параметры, переданные методу
     * @throws ContainerExceptionInterface
     * @throws ReflectionException|Throwable
     */
    public function call(callable $callable, array $arguments = [])
    {
        if ($callable instanceof Closure || is_string($callable)) {
            return $this->callFunc($callable, $arguments);
        }
        [$objectOrClass, $method] = $callable;
        $isObject = is_object($objectOrClass);
        $reflectionMethod = Reflection::reflectMethod($isObject ? get_class($objectOrClass) : $this->getBinding($objectOrClass), $method);
        if (false === $reflectionMethod->isAbstract()) {
            if (!$reflectionMethod->isPublic()) {
                $reflectionMethod->setAccessible(true);
            }
            return $reflectionMethod->invokeArgs(
                $reflectionMethod->isStatic() ? null : ($isObject ? $objectOrClass : $this->make($objectOrClass)),
                $this->getFuncArgs($reflectionMethod, $arguments)
            );
        }
        throw new BadMethodCallException('Невозможно вызвать метод: ' . $method);
    }

    /**
     * Закрытие вызова
     *
     * @param Closure|string $function функция
     * @param array $arguments список параметров
     * @throws ReflectionException|NotFoundException|ContainerExceptionInterface
     */
    public function callFunc($function, array $arguments = [])
    {
        $reflectFunction = new ReflectionFunction($function);

        return $reflectFunction->invokeArgs(
            $this->getFuncArgs($reflectFunction, $arguments)
        );
    }

    /**
     * Получить параметры конструктора
     *
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function getConstructorArgs(ReflectionClass $reflectionClass, array $arguments = []): array
    {
        if (is_null($constructor = $reflectionClass->getConstructor())) {
            return $arguments;
        }
        if ($reflectionClass->isInstantiable()) {
            return $this->getFuncArgs($constructor, $arguments);
        }
        throw new ContainerException('Не удается инициализировать класс: ' . $reflectionClass->getName(), 599);
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction способ отражения
     * @param array $arguments список параметров, поддерживает ассоциативные массивы и будет автоматически передаваться в соответствии с именем переменной
     * @throws ContainerExceptionInterface|ReflectionException
     * @throws Throwable
     */
    public function getFuncArgs(ReflectionFunctionAbstract $reflectionFunction, array $arguments = []): array
    {
        $objectValues = $funcArgs = [];
        foreach ($arguments as $argument) {
            if (is_object($argument)) {
                $objectValues[get_class($argument)] = $argument;
            }
        }
        foreach ($reflectionFunction->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $arguments)) {
                $funcArgs[] = $arguments[$name];
                unset($arguments[$name]);
            } else {
                $type = $parameter->getType();
                if (is_null($type)
                    || ($type instanceof ReflectionNamedType && $type->isBuiltin())
                    || (PHP_VERSION_ID >= 80000 && $type instanceof ReflectionUnionType)
                    || ($typeName = $type->getName()) === 'Closure') {
                    $funcArgs[] = $parameter->isOptional() ? $parameter->getDefaultValue() : null;
                } else {
                    if (isset($objectValues[$typeName])) {
                        $funcArgs[] = $objectValues[$typeName];
                    } else {
                        try {
                            $funcArgs[] = $this->make($typeName);
                        } catch (Throwable $throwable) {
                            if (!$parameter->isOptional()) {
                                throw $throwable;
                            }
                            $funcArgs[] = $parameter->getDefaultValue();
                        }
                    }
                }
            }
        }
        return array_map(fn($value) => is_null($value) && !empty($arguments) ? array_shift($arguments) : $value, $funcArgs);
    }

}