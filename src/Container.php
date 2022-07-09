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
     * @var array соответствие между классом и идентичностью
     */
    protected array $bindings = [];

    /**
     * @var array разрешенный экземпляр
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
        return $this->resolved[$this->getBinding($id)] ?? new NotFoundException('Экземпляр не найден: ' . $id);
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
            $reflectionClass = Reflection::class($id);
            if ($reflectionClass->isInterface()) {
                if (!$this->bound($id)) {
                    throw new ContainerException($id . ' не имеет класса реализации.', 600);
                }
                $reflectionClass = Reflection::class($this->getBinding($id));
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
        $binding = $this->getBinding($id);
        if (isset($this->resolved[$binding])) {
            unset($this->resolved[$binding]);
        }
        if ($id !== $binding && isset($this->resolved[$id])) {
            unset($this->resolved[$id]);
        }
    }

    /**
     * Вызов метода класса
     *
     * @param array|string|Closure $callable массив вызываемых классов или экземпляров и методов
     * @param array $arguments параметры, переданные методу (ассоциативный массив)
     * @return mixed
     * @throws ReflectionException
     */
    public function call(array|string|Closure $callable, array $arguments = [])
    {
        if ($callable instanceof Closure || is_string($callable)) {
            return $this->callFunc($callable, $arguments);
        }
        [$objectOrClass, $method] = $callable;
        $isObject = is_object($objectOrClass);
        $reflectionMethod = Reflection::method($isObject ? get_class($objectOrClass) : $this->getBinding($objectOrClass), $method);
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
     * @param array $arguments список параметров (ассоциативный массив)
     * @throws ReflectionException|NotFoundException
     * @throws ContainerExceptionInterface
     */
    public function callFunc(string|Closure $function, array $arguments = [])
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
     * Типы преобразования
     * @param $value
     * @param string $type
     * @return mixed
     */
    protected function castParameter($value, string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'string' => (string)$value,
            'bool' => (bool)$value,
            'array' => (array)$value,
            'float' => (float)$value,
            'double' => (double)$value,
            'object' => (object)$value,
            default => $value,
        };
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction метод отражения
     * @param array $arguments список параметров (ассоциативный массив)
     * @return array
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    public function getFuncArgs(ReflectionFunctionAbstract $reflectionFunction, array $arguments = []): array
    {
        $funcArgs = [];
        foreach ($reflectionFunction->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $arguments)) {
                $injectValue = $arguments[$name];
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->isBuiltin()) {
                    $injectValue = $this->castParameter($injectValue, $type->getName());
                }
                $funcArgs[] = $injectValue;
            } else {
                $type = $parameter->getType();
                if (is_null($type)
                    || ($type instanceof ReflectionNamedType && $type->isBuiltin())
                    || $type instanceof ReflectionUnionType
                    || ($typeName = $type->getName()) === 'Closure'
                ) {
                    $funcArgs[] = $parameter->isOptional()
                        ? $parameter->getDefaultValue()
                        : throw new ContainerException(sprintf('Отсутствует параметр `%s`', $name));
                } else {
                    try {
                        $funcArgs[] = $this->make($typeName);
                    } catch (ReflectionException | ContainerExceptionInterface $exception) {
                        $funcArgs[] = $parameter->isOptional() ? $parameter->getDefaultValue() : throw $exception;
                    }
                }
            }
        }
        return $funcArgs;
    }

}