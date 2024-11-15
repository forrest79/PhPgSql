<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\Sql;

use Forrest79\PhPgSql\Db;

class Query extends Expression
{
	private Db\Query|NULL $dbQuery = NULL;


	public function toDbQuery(): Db\Query
	{
		if ($this->dbQuery === NULL) {
			$this->dbQuery = Db\SqlDefinition::createQuery($this->getSqlDefinition());
		}

		return $this->dbQuery;
	}

}
