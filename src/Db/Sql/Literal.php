<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Sql;

use Forrest79\PhPgSql\Db;

class Literal implements Db\Sql
{
	private Db\SqlDefinition $sqlDefinition;


	final public function __construct(string $value)
	{
		$this->sqlDefinition = new Db\SqlDefinition($value, []);
	}


	public function getSqlDefinition(): Db\SqlDefinition
	{
		return $this->sqlDefinition;
	}


	public static function create(string $value): self
	{
		return new self($value);
	}

}
