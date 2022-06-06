<?php

declare(strict_types=1);

use PCore\Di\Context;
use PCore\Di\Exceptions\NotFoundException;
use Psr\Container\{ContainerExceptionInterface, ContainerInterface};

/**
 * @github https://github.com/pcore-framework/di
 */
if (false === function_exists('container')) {
    /**
     * Создание экземпляра контейнера и получение экземпляров
     */
    function container(): ContainerInterface
    {
        return Context::getContainer();
    }
}

if (false === function_exists('call')) {
    /**
     * Метод вызова контейнера
     *
     * @param callable $callback массив, замыкание, имя функции
     * @param array $arguments список параметров, переданных методу
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    function call(callable $callback, array $arguments = [])
    {
        return container()->call($callback, $arguments);
    }
}

if (false === function_exists('make')) {
    /**
     * @param string $id
     * @param array $parameters
     * @return object
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     */
    function make(string $id, array $parameters = []): object
    {
        return container()->make($id, $parameters);
    }
}