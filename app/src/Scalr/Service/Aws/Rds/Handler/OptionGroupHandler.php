<?php
namespace Scalr\Service\Aws\Rds\Handler;

use Scalr\Service\Aws\Rds\DataType\OptionGroupsList;
use Scalr\Service\Aws\RdsException;
use Scalr\Service\Aws\Rds\AbstractRdsHandler;

/**
 * Amazon RDS OptionGroupHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class OptionGroupHandler extends AbstractRdsHandler
{
    /**
     * OptionGroupHandler action
     * Describes the available option groups.
     *
     * @param string $engineName
     * @param string $majorEngineVersion
     * @param string $marker
     * @param int    $maxRecords
     * @return OptionGroupsList
     * @throws RdsException
     */
    public function describe($engineName = null, $majorEngineVersion = null, $marker = null, $maxRecords = null)
    {
        return $this->getRds()->getApiHandler()->describeOptionGroups($engineName, $majorEngineVersion, $marker, $maxRecords);
    }

}