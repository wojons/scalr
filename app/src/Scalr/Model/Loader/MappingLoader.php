<?php
namespace Scalr\Model\Loader;

use Scalr\Exception\ModelException;
use Scalr\Model\Mapping\Column;

/**
 * Mapping loader
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.03.2014)
 */
class MappingLoader
{

    /**
     * @var array
     */
    protected $mappingClasses;

    public function __construct()
    {
        $this->mappingClasses = array();
        $iterator = new \GlobIterator(__DIR__ . '/../Mapping/*.php', \FilesystemIterator::KEY_AS_FILENAME);
        foreach ($iterator as $fileInfo) {
            /* @var $fileInfo \SplFileInfo */
            $this->mappingClasses[substr($fileInfo->getFilename(), 0, -4)] = true;
        }
    }

    /**
     * Gets mapping classes
     *
     * @return  array  Returns the list of the available annotation mapping classes
     */
    public function getMappingClasses()
    {
        return $this->mappingClasses;
    }

    /**
     * Checks whether mapping class exists
     *
     * @param   string    $name
     * @return  boolean   Returns true on success
     */
    public function hasMappingClass($name)
    {
        return !empty($this->mappingClasses[$name]);
    }

    /**
     * Loads field or class annotation
     *
     * @param   \ReflectionProperty|\ReflectionClass  $refl Reflection property or class
     */
    public function load($refl)
    {
        if ($refl instanceof \ReflectionProperty || $refl instanceof \ReflectionClass) {
            $str =  join('|', array_map('preg_quote', array_keys($this->mappingClasses)));

            $comment = $refl->getDocComment();

            $annotation = $refl instanceof \ReflectionProperty ? new Field() : new Entity();
            $annotation->name = $refl->name;

            if (preg_match_all('/^\s+\*\s+@(' . $str . ')(.*)$/m', $comment, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $mappingClass = 'Scalr\\Model\\Mapping\\' . $m[1];
                    $annotationProperty = lcfirst($m[1]);

                    $mapping = new $mappingClass();

                    if (!empty($m[2])) {
                        $definition = trim($m[2], "\r\t\n ()");
                        if ($definition) {
                            if (method_exists($mapping, '__invoke')) {
                                $mapping(trim($definition, "\"'"));
                            } else {
                                $str = '{' . preg_replace('/([\w"]+)\s*=/', '"$1":', $definition). '}';
                                $type = json_decode($str, true);
                                if ($type) {
                                    foreach (get_class_vars($mappingClass) as $k => $defaultValue) {
                                        if (isset($type[$k])) {
                                            $mapping->$k = $type[$k];
                                        }
                                    }
                                } else {
                                    throw new ModelException(sprintf(
                                        "Invalid annotation '%s' for %s of %s",
                                        $m[0], $refl->name, (isset($refl->class) ? $refl->class : 'class')
                                    ));
                                }
                            }
                        }
                    }

                    $annotation->$annotationProperty = $mapping;
                }
            }

            if ($refl instanceof \ReflectionProperty) {
                if (!($annotation->column instanceof Column)) {
                    $annotation->column = new Column();
                    $annotation->column->type = 'string';
                }

                if (!$annotation->column->name) {
                    $annotation->column->name = \Scalr::decamelize($annotation->name);
                }
            }

            $refl->annotation = $annotation;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Either ReflectionProperty or ReflectionClass is expected, "%s" passed.',
                (is_object($refl) ? get_class($refl) : gettype($refl))
            ));
        }
    }
}