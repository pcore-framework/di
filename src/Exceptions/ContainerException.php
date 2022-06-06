<?php

declare(strict_types=1);

namespace PCore\Di\Exceptions;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Class ContainerException
 * @package PCore\Di\Exceptions
 * @github https://github.com/pcore-framework/di
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}