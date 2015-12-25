<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\Constraint;

use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\AbstractSpecObject;

/**
 * Class ResponseBodyConstraint
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (03.12.2015)
 */
class ResponseBodyConstraint extends \PHPUnit_Framework_Constraint
{
    /**
     * @var AbstractSpecObject
     */
    public $specObject;

    /**
     * @var Validator
     */
    public $validator;

    /**
     * ResponseBodyConstraint constructor.
     *
     * @param AbstractSpecObject $specObject
     */
    public function __construct(AbstractSpecObject $specObject)
    {
        parent::__construct();
        $this->specObject = $specObject;
        $this->validator = new Validator();
    }

    /**
     * {@inheritdoc}
     * @see \PHPUnit_Framework_Constraint::matches()
     */
    protected function matches($other)
    {
        $this->validator->check($other, $this->specObject);
        return $this->validator->isValid();
    }

    /**
     * {@inheritdoc}
     * @see \PHPUnit_Framework_Constraint::additionalFailureDescription()
     */
    protected function additionalFailureDescription($other)
    {
        $description = $this->specObject->getObjectName() . " has errors \n";
        foreach ($this->validator->getErrors() as $error) {
            $description .= sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
        return $description;
    }

    /**
     *{@inheritdoc}
     * @see \PHPUnit_Framework_Constraint::toString()
     */
    public function toString()
    {
        return $this->specObject->getObjectName();
    }
}