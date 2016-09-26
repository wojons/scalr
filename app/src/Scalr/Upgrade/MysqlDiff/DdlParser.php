<?php

namespace Scalr\Upgrade\MysqlDiff;

use Scalr\Exception\NotYetImplementedException;
use Scalr\Upgrade\MysqlDiff\Mapping\Column;
use Scalr\Upgrade\MysqlDiff\Mapping\ForeignKey;
use Scalr\Upgrade\MysqlDiff\Mapping\Index;
use Scalr\Upgrade\MysqlDiff\Mapping\IndexColumn;
use Scalr\Upgrade\MysqlDiff\Mapping\Scheme;
use Scalr\Upgrade\MysqlDiff\Mapping\Table;
use Scalr\Upgrade\MysqlDiff\Mapping\Type;
use Scalr\Util\ObjectAccess;
use SplFileObject;

/**
 * Class DdlParser
 * @package Scalr\Upgrade\MysqlDiff
 */
class DdlParser
{

    /**
     * sql comment test
     */
    const DDL_COMMENT_REGEX = '/^(\-\-)|(\/\*)|#/';

    /**
     * capturing groups:
     *  tmp:  temporary modifier
     *  exists:  IF NOT EXISTS check
     *  name:  table name
     */
    const CREATE_TABLE_REGEX = '/^CREATE(?P<tmp>\s+TEMPORARY)?\s+TABLE(?P<exists>\s+IF NOT EXISTS)?\s+`(?P<name>.+?)`\s*\(/i';

    /**
     * capturing groups:
     *  name:   option name
     *  val:    simple option value
     *  sval:   string option value
     */
    const TABLE_OPTIONS_REGEX = "/(\s*(?P<name>[^)=']+?)=(((?P<val>[^\s']+?)[ ;])|('(?P<sval>.+?)'[ ;])))/i";

    /**
     * capturing groups:
     *  name:       column name
     *  type:       column type
     *  null:       nullable
     *  def:        default value
     *  ai:         auto increment
     *  key:        index
     *  comment:    comment
     *  format:     column format
     *  ref:        references
     */
    const FIELD_REGEX = '/^`(?P<name>.+?)`\s+(?P<type>.+?)(\s+(?P<null>(NOT NULL)|(NULL)))?(\s+DEFAULT\s+(?P<def>.+?))?(?P<ai>\s+AUTO_INCREMENT)?(\s+(?P<key>(((UNIQUE)|(PRIMARY))(\s+KEY)?)|(KEY)))?(\s+COMMENT\s+\'(?P<comment>.*?)\')?(\s+COLUMN_FORMAT\s+(?P<format>(FIXED)|(DYNAMIC)|(DEFAULT)))?(?P<ref>\s+REFERENCES\s+`(.+?)`\s+\((.+?)\)(\s+MATCH\s+((FULL)|(PARTIAL)|(SIMPLE)))?(\s+ON\s+((DELETE)|(UPDATE))\s+((RESTRICT)|(CASCADE)|(SET NULL)|(NO ACTION))){0,2})?,?$/i';

    /**
     * capturing groups:
     *  type:       field type
     *  vals:       values range
     *  us:         unsigned
     *  zf:         zerofill
     *  bin:        binary
     *  charset:    field character set
     *  collate:    field collation
     */
    const FIELD_TYPE_REGEX = '/^(?P<type>.+?)(\((?P<vals>.*?)\))?(?P<us>\s+UNSIGNED)?(?P<zf>\s+ZEROFILL)?(?P<bin>\s+BINARY)?(\s+CHARACTER SET\s+(?P<charset>.+?))?(\s+COLLATE\s+(?P<collate>.+?))?$/i';

    /**
     * capturing groups:
     *  name:  key name
     *  type:  index type
     *  cols:  column names
     */
    const PRIMARY_KEY_REGEX = '/^((CONSTRAINT\s+`(?<name>.+?)`)\s+)?PRIMARY KEY(?P<type>\s+USING\s+((BTREE)|(HASH)))?\s+\((?P<cols>.+?)\),?$/i';

    /**
     * capturing groups:
     *  name|name1:     name
     *  cols:           columns
     *  refOpt:         reference definition
     *      refTable:       referenced table
     *      refCols:        referenced columns
     *      match:          match type
     */
    const FOREIGN_KEY_REGEX = '/^((CONSTRAINT\s+`(?P<name>.+?)`)\s+)?FOREIGN KEY(\s+`(?P<name1>.+?)`)?\s+\((?P<cols>.+?)\)\s+(?P<refOpt>REFERENCES\s+`(?P<refTable>.+?)`\s+\((?P<refCols>.+?)\)(\s+MATCH\s+(?P<match>(FULL)|(PARTIAL)|(SIMPLE)))?(\s+ON\s+((DELETE)|(UPDATE))\s+((RESTRICT)|(CASCADE)|(SET NULL)|(NO ACTION))){0,2}),?$/i';

    /**
     * capturing groups:
     *  event:      referential event
     *  action:     action
     */
    const REFERENCE_ACTIONS_REGEX = '/(\s+ON\s+(?P<event>(DELETE)|(UPDATE))\s+(?P<action>(RESTRICT)|(CASCADE)|(SET NULL)|(NO ACTION)))/i';

    /**
     * capturing groups:
     *  name|name1:     index name
     *  type:           index type
     *  cols:           column names
     */
    const UNIQUE_KEY_REGEX = '/^((CONSTRAINT\s+`(?P<name>.+?)`)\s+)?UNIQUE(\s+((INDEX)|(KEY)))?(\s+`(?P<name1>.+?)`)?(\s+USING\s+(?P<type>(BTREE)|(HASH)))?\s+\((?P<cols>.+?)\),?$/i';

    /**
     * capturing groups:
     *  type:   index type
     *  name:   index name
     *  cols:   column names
     */
    const FULLTEXT_KEY_REGEX = '/^(?P<type>(FULLTEXT)|(SPATIAL))(\s+((INDEX)|(KEY)))?(\s+`(?P<name>.+?)`)?\s+\((?P<cols>.+?)\),?$/i';

    /**
     * capturing groups:
     *  name:   index name
     *  type:   index type
     *  cols:   column names
     */
    const INDEX_REGEX = '/^INDEX|KEY(\s+`(?P<name>.+?)`)?(\s+USING\s+(?P<type>(BTREE)|(HASH)))?\s+\((?P<cols>.+?)\),?$/i';

    /**
     * capturing groups:
     *  name:   column name
     *  length: indexed length
     *  dir:    index direction
     */
    const INDEX_COL_NAME_REGEX = '/`(?P<name>.*?)`(\((?P<length>\d+)\))?(?P<dir>\s+((ASC)|(DESC)))?/i';

    /**
     * Input data stream
     * @var SplFileObject
     */
    private $input;

    /**
     * Parsed scheme
     * @var Scheme
     */
    private $scheme;

    /**
     * Current DDL line
     *
     * @var int
     */
    private $line;

    /**
     * Current line tokens
     *
     * @var array
     */
    private $tokens;

    /**
     * Retrieves column pointers
     *
     * @param   Table        $table The table of the column references
     * @param   array|string $names The names of the columns
     *
     * @return  IndexColumn[]   Returns the pointers for the columns
     */
    private static function getColumnsRef(Table $table, $names)
    {
        preg_match_all(static::INDEX_COL_NAME_REGEX, $names, $matches);

        $columns = [ ];

        foreach ($matches['name'] as $i => $name) {
            $columns[ $name ] = new IndexColumn(
                $table->columns[ $name ], $matches['length'][ $i ], $matches['dir'][ $i ]
            );
        }

        return $columns;
    }

    /**
     * Parse the type of the fields
     *
     * @param   string $str Type part DDL
     *
     * @return  Type    Returns The type of the column
     */
    private static function parseType($str)
    {
        preg_match(static::FIELD_TYPE_REGEX, $str, $matches_);

        $matches = new ObjectAccess($matches_);

        return new Type(
            strtoupper($matches['type']),
            $matches['vals'],
            strtoupper($matches['us']),
            strtoupper($matches['zf']),
            strtoupper($matches['bin']),
            $matches['charset'],
            $matches['collate']
        );
    }

    /**
     * DDLParser
     *
     * @param   SplFileObject $input input stream
     */
    public function __construct(SplFileObject $input)
    {
        $this->input  = $input;
        $this->scheme = new Scheme();
    }

    /**
     * Set new input stream
     *
     * @param   SplFileObject $input
     */
    public function setInput(SplFileObject $input)
    {
        $this->input = $input;
    }

    /**
     * Gets parsed scheme
     *
     * @return  Scheme Returns parsed scheme
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Reads next line from input stream to buffer.
     *
     * Discards comments.
     *
     * @return  bool Returns true on success or false otherwise
     */
    public function nextLine()
    {
        while (!$this->input->eof()) {
            $this->line = trim($this->input->fgets());

            if (preg_match(static::DDL_COMMENT_REGEX, $this->line)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Parse CREATE statement
     */
    public function create()
    {
        switch (array_shift($this->tokens)) {
            case 'TABLE':
                $this->scheme->addTable($this->parseCreateTable());
                break;
            case 'INDEX':
                break;
            default:
                break;
        }
    }

    /**
     * Parses ALTER statement
     *
     * @throws  NotYetImplementedException
     */
    public function alter()
    {
        throw new NotYetImplementedException("ALTER statement");
    }

    /**
     * Parse DROP statement
     * @throws  NotYetImplementedException
     */
    public function drop()
    {
        throw new NotYetImplementedException("DROP statement");
    }

    /**
     * Parse RENAME statement
     * @throws  NotYetImplementedException
     */
    public function rename()
    {
        throw new NotYetImplementedException("RENAME statement");
    }

    /**
     * Parse SCHEMA from input stream
     */
    public function parse()
    {
        while ($this->nextLine()) {
            $this->tokens = preg_split('/\s+/', trim($this->line));

            switch (array_shift($this->tokens)) {
                case 'CREATE':
                    $this->create();
                    break;
                case 'ALTER':
                    $this->alter();
                    break;
                case 'DROP':
                    $this->drop();
                    break;
                case 'RENAME':
                    $this->rename();
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Parse CREATE TABLE statement
     *
     * @return  Table
     */
    public function parseCreateTable()
    {
        $pregMatch = function ($pattern, $string, &$matches, $all = false) {
            $arr     = [ ];
            $matches = new ObjectAccess($arr);

            return $all ? preg_match_all($pattern, $string, $arr) : preg_match($pattern, $string, $arr);
        };

        $matches = null;

        $pregMatch(static::CREATE_TABLE_REGEX, $this->line, $matches);

        $tableName = $matches['name'];

        if ($table = $this->scheme[ $tableName ]) {
            $table->temporary = $matches['tmp'];
        } else {
            $table = new Table($tableName, $matches['tmp']);
        }

        $matches = [ ];

        while ($this->nextLine()) {
            if ($pregMatch(static::FIELD_REGEX, $this->line, $matches)) {
                $table->addColumn(
                    new Column(
                        $matches['name'],
                        static::parseType($matches['type']),
                        $matches['null'],
                        $matches['def'],
                        $matches['ai'],
                        trim($matches['key']),
                        $matches['comment'],
                        $matches['format'],
                        trim($matches['ref'])
                    )
                );
            } else if (strpos($this->line, 'PRIMARY KEY') !== false) {
                $pregMatch(static::PRIMARY_KEY_REGEX, $this->line, $matches);

                $columns = static::getColumnsRef($table, $matches['cols']);

                $table->addIndex(new Index($matches['name'], $columns, 'PRIMARY', $matches['type']));
            } else if (strpos($this->line, 'FOREIGN KEY') !== false) {
                $pregMatch(static::FOREIGN_KEY_REGEX, $this->line, $matches);

                preg_match_all(static::REFERENCE_ACTIONS_REGEX, $matches['refOpt'], $options);

                $actions = [ ];
                foreach ($options['event'] as $i => $event) {
                    if (isset($options['action'][ $i ])) {
                        $actions[ $event ] = $options['action'][ $i ];
                    }
                }

                $columns = static::getColumnsRef($table, $matches['cols']);

                $refTableName = $matches['refTable'];
                if (!isset($this->scheme[ $refTableName ])) {
                    $this->scheme[ $refTableName ] = new Table($refTableName);
                }

                $refColumns = static::getColumnsRef($this->scheme[ $refTableName ], $matches['refCols']);

                $fk = new ForeignKey(
                    $matches['name'] ?: $matches['name1'], $table, $columns, $this->scheme[ $refTableName ],
                    $refColumns, $matches['match'], $actions
                );

                $table->addIndex($fk);

                $this->scheme->referencedTables[ $fk->refTable->name ][] = &$table->fks[ $fk->name ];

            } else if (strpos($this->line, 'UNIQUE') !== false) {
                $pregMatch(static::UNIQUE_KEY_REGEX, $this->line, $matches);
                $table->addIndex(
                    new Index(
                        $matches['name'] ?: $matches['name1'], static::getColumnsRef($table, $matches['cols']),
                        'UNIQUE', $matches['type']
                    )
                );

            } else if ($pregMatch(static::FULLTEXT_KEY_REGEX, $this->line, $matches)) {
                $table->addIndex(
                    new Index($matches['name'], static::getColumnsRef($table, $matches['cols']), $matches['type'], null)
                );

            } else if ($pregMatch(static::INDEX_REGEX, $this->line, $matches)) {
                $table->addIndex(
                    new Index(
                        $matches['name'], static::getColumnsRef($table, $matches['cols']), 'INDEX', $matches['type']
                    )
                );

            } else if ($this->line[0] == ')') {
                $pregMatch(static::TABLE_OPTIONS_REGEX, $this->line, $matches, true);
                $options = $matches['name'];
                $values1 = $matches['val'];
                $values2 = $matches['sval'];

                foreach ($options as $key => $value) {
                    $table->setOption($value, $values1[ $key ] ? $values1[ $key ] : $values2[ $key ]);
                }
                break;
            }
        }

        return $table;
    }

    /**
     * Parse CREATE INDEX statement
     * @throws  NotYetImplementedException
     */
    public function parseCreateIndex()
    {
        throw new NotYetImplementedException("CREATE INDEX statemet");
    }

    /**
     * Parse ALTER TABLE statement
     * @throws  NotYetImplementedException
     */
    public function parseAlterTable()
    {
        throw new NotYetImplementedException("ALTER TABLE statement");
    }

    /**
     * Parse DROP TABLE statement
     * @throws  NotYetImplementedException
     */
    public function parseDropTable()
    {
        throw new NotYetImplementedException("DROP TABLE statement");
    }

    /**
     * Parse DROP INDEX statement
     * @throws  NotYetImplementedException
     */
    public function parseDropIndex()
    {
        throw new NotYetImplementedException("DROP INDEX statement");
    }

    /**
     * Parse RENAME TABLE statement
     * @throws  NotYetImplementedException
     */
    public function parseRenameTable()
    {
        throw new NotYetImplementedException("RENAME TABLE statement");
    }
}
