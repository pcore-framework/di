<?php

declare(strict_types=1);

namespace PCore\Di\Annotations;

use Attribute;
use PCore\Aop\Contracts\PropertyAttribute;
use PCore\Aop\Exceptions\PropertyHandleException;
use PCore\Di\{Context, Reflection};
use Throwable;

/**
 * Class Inject
 * @package PCore\Di\Annotations
 * @github https://github.com/pcore-framework/di
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject implements PropertyAttribute
{

    /**
     * @param string|null $id тип инъекции
     */
    public function __construct(protected ?string $id = null)
    {
    }

    public function handle(object $object, string $property): void
    {
        try {
            $container = Context::getContainer();
            $reflectionProperty = Reflection::property($object::class, $property);
            if ((!is_null($type = $reflectionProperty->getType()) && $type = $type->getName()) || $type = $this->id) {
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, $container->make($type));
            }
        } catch (Throwable $throwable) {
            throw new PropertyHandleException('Не удалось назначить свойство.' . $throwable->getMessage());
        }
    }

}