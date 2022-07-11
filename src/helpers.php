<?php

declare(strict_types=1);

use PCore\Di\Context;
use PCore\Di\Exceptions\NotFoundException;
use Psr\Container\{ContainerExceptionInterface, ContainerInterface};

/**
 * @github https://github.com/pcore-framework/di
 */
if (function_exists('container') === false) {
    /**
     * Создание экземпляра контейнера и получение экземпляров
     */
    function container(): ContainerInterface
    {
        return Context::getContainer();
    }
}

if (function_exists('call') === false) {
    /**
     * Метод вызова контейнера
     *
     * @param array|string|Closure $callback массив, замыкание, имя функции
     * @param array $arguments список параметров, переданных методу
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    function call(array|string|Closure $callback, array $arguments = [])
    {
        return container()->call($callback, $arguments);
    }
}

if (function_exists('make') === false) {
    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     * @throws ContainerExceptionInterface|ReflectionException
     * @throws NotFoundException
     */
    function make(string $id, array $parameters = [])
    {
        return container()->make($id, $parameters);
    }
}