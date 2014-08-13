<?php
namespace Scalr\Model\Type;

use Scalr\Model\Loader\Field;

/**
 * AbstractType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (07.02.2014)
 */
abstract class AbstractType implements TypeInterface
{
    /**
     * Field properties
     *
     * @var Field
     */
    public $field;

    /**
     * Function is used to select value from database
     *
     * @var string
     */
    protected $sel = '?';

    /**
     * Function is used to search value in database
     *
     * @var string
     */
    protected $wh = '?';

    /**
     * Constructor
     *
     * @param   Field    $field  The field object
     */
    public function __construct(Field $field)
    {
        $this->field = $field;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\TypeInterface::toDb()
     */
    public function toDb($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\TypeInterface::toPhp()
     */
    public function toPhp($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\TypeInterface::sel()
     */
    public function sel()
    {
        return $this->sel;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Type\TypeInterface::wh()
     */
    public function wh()
    {
        return $this->wh;
    }
}