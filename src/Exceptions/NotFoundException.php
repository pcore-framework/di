<?php

declare(strict_types=1);

namespace PCore\Di\Exceptions;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Class NotFoundException
 * @package PCore\Di\Exceptions
 * @github https://github.com/pcore-framework/di
 */
class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}