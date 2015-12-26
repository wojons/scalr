<?php

namespace Scalr\UI\Request;

/**
 * FileUploadData
 *
 * Provide access to file's content of uploaded file
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0.0 (19.06.2014)
 */
class FileUploadData implements ObjectInitializingInterface
{
    /**
     * @var string path to file in filesystem
     */
    protected $fileName;

    /**
     * {@inheritdoc}
     * @see     ObjectInitializingInterface::initFromRequest()
     * @return  null|FileUploadData
     */
    public static function initFromRequest($value, $name = '')
    {
        return $value ? new self($value) : NULL;
    }

    /**
     * @param string $file
     * @throws \Exception
     */
    public function __construct($file)
    {
        $this->fileName = $file;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return file_get_contents($this->fileName);
    }
}
