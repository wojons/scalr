<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * StorageProfile
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\ImageReference  $imageReference
 *            Required for platform images, marketplace images and Linux virtual machines.
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\OsDisk  $osDisk
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\DataDiskList  $dataDisks
 *            Specifies the parameters that are used to add a data disk to a virtual machine.
 *
 */
class StorageProfile extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['imageReference', 'osDisk', 'dataDisks'];

    /**
     * Constructor
     *
     * @param   array|OsDisk     $osDisk     Specifies the os disk data.
     */
    public function __construct($osDisk)
    {
        $this->setOsDisk($osDisk);
    }

    /**
     * Sets OsDisk
     *
     * @param   array|OsDisk $osDisk
     * @return  StorageProfile
     */
    public function setOsDisk($osDisk = null)
    {
        if (!($osDisk instanceof OsDisk)) {
            $osDisk = OsDisk::initArray($osDisk);
        }

        return $this->__call(__FUNCTION__, [$osDisk]);
    }

    /**
     * Sets ImageReference
     *
     * @param   array|ImageReference $imageReference Required for platform images, marketplace images and Linux virtual machines.
     * @return  StorageProfile
     */
    public function setImageReference($imageReference = null)
    {
        if (!($imageReference instanceof ImageReference)) {
            $imageReference = ImageReference::initArray($imageReference);
        }

        return $this->__call(__FUNCTION__, [$imageReference]);
    }

    /**
     * Sets DataDiskList
     *
     * @param   array|DataDiskList $dataDisks
     * @return  StorageProfile
     */
    public function setDataDisks($dataDisks = null)
    {
        if (!($dataDisks instanceof DataDiskList)) {
            $dataDiskList = new DataDiskList();

            foreach ($dataDisks as $dataDisk) {
                if (!($dataDisk instanceof DataDiskData)) {
                    $dataDiskData = DataDiskData::initArray($dataDisk);
                } else {
                    $dataDiskData = $dataDisk;
                }

                $dataDiskList->append($dataDiskData);
            }
        } else {
            $dataDiskList = $dataDisks;
        }

        return $this->__call(__FUNCTION__, [$dataDiskList]);
    }

}