<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

class Connection extends Db\Connection
{
	private QueryBuilder|NULL $queryBuilder = NULL;


	public function setQueryBuilder(QueryBuilder $queryBuilder): self
	{
		$this->queryBuilder = $queryBuilder;

		return $this;
	}


	protected function getQueryBuilder(): QueryBuilder
	{
		if ($this->queryBuilder === NULL) {
			$this->queryBuilder = new QueryBuilder();
		}

		return $this->queryBuilder;
	}


	public function createQuery(): QueryExecute
	{
		return new QueryExecute($this->getQueryBuilder(), $this);
	}

}
