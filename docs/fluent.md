# PhPgSql\Fluent

PhPgSql\Fluent implements a small part of PostgreSQL SQL [commands](https://www.postgresql.org/docs/current/reference.html) via fluent object syntax.

## Common use

Fluent interface can be used to simply create SQL queries using objects.

Fluent methods are defined in the `Forrest79\PhPgSql\Fluent\Sql` interface. There are three objects implementing this interface. You can start your query in the same way from all these objects:

- `Forrest79\PhPgSql\Fluent\Query` - this is the basic object, that generates queries (but can't execute them)
- `Forrest79\PhPgSql\Fluent\QueryExecute` - this is `Fluent\Query` object extension, requires `Db\Connection` object and can execute queries in the database
- `Forrest79\PhPgSql\Fluent\Connection` - this is `Db\Connection` extension that creates `Fluent\QueryExecute` object with the correct `Db\Connection` - you will be probably using this most

Both `Query` and `QueryExecute` needs the `QueryBuilder` object. `Fluent\Connection` pass this object automatically.

Fluent generates `Db\Sql\Query` object with the `?` character as placeholders for parameters that is handled by `PhPgSql\Db` part. `Db\Sql\Query` object is created and used internally, but you can create it manually if you want, with the `Fluent\Query::createSqlQuery()` method. 

```php
$fluent = new Forrest79\PhPgSql\Fluent\Query(new Forrest79\PhPgSql\Fluent\QueryBuilder());

$query = $fluent
  ->select(['*'])
  ->from('users')
  ->where('id', 1)
  ->toDbQuery();

dump($query->sql); // (string) 'SELECT * FROM users WHERE id = $1'
dump($query->params); // (array) [1]
```

With the `QueryExecute` object you can run this query in DB. This object has all `fetch*()` methods as the `Db\Result` object.

```php
$fluent = new Forrest79\PhPgSql\Fluent\QueryExecute(new Forrest79\PhPgSql\Fluent\QueryBuilder(), $connection);

$row = $fluent
  ->select(['*'])
  ->from('users')
  ->where('id', 1)
  ->fetch();

dump($row); // (Row) ['id' => 1, 'nick' => 'Bob', 'inserted_datetime' => '2020-01-01 09:00:00', 'active' => TRUE, 'age' => 45, 'height_cm' => 178.2, 'phones' => [200300, 487412]]
```

But you don't want to do this so complicatedly. Use `Fluent\Connection` to create `QueryExecute` easily:

```php
$userNick = $connection
  ->createQuery()
  ->select(['nick'])
  ->from('users')
  ->where('id', 2)
  ->fetchSingle();

dump($userNick); // (string) 'Brandon'
```

## Writing SQL queries

This is the list of all methods you can use to generate a query. This covers most of the default SQL commands. If there is something missing - you must write your query manually as a string (or you can extend `Fluent\Query` and `Fluent\QueryBuilder` with your functionality - more about this later). Many methods define alias, some need it and can't be used without it.

You can start a query with whatever method you want. Methods only set query properties, and from these properties are generated the final SQL query.

Every query is `SELECT` at first, until you call `->insert(...)`, `->update(...)`, `->delete(...)` or `->truncate(...)`, which change query type to appropriate SQL command (you can set type more than once in one query, the last is used - except `INSERT` - `SELECT`). So you can prepare you query in a common way and at the end, you can decide if you want to `SELECT` or `DELETE` data or whatsoever. If you call some method more than once, data is merged, for example, this `->select(['column1'])->select(['column2'])` is the same as `->select(['column1', 'column2'])`. Conditions are connected with the logic `AND`.

- `table($table, ?string $alias = NULL)` - creates `Query` object with defined main table - this table can be used for selects, updates, inserts, deletes or truncate - you don't need to use concrete method to define table. `$table` can be simple `string` or other `Query` or `Db\Sql`.


- `select(array $columns)` - defines columns (array `key => column`) to `SELECT`. String array key is column alias. Column can be `string`, `int`, `bool`, `Query`, `Db\Sql` or `NULL`


- `distinct(): Query` - creates `SELECT DISCTINCT`


- `distinctOn(array $on): Query` - creates `SELECT DISCTINCT ON(...)`. `$on` is a list of strings.


- `from($from, ?string $alias = NULL)` - defines table for `SELECT` query. `$from` can be simple `string` or other `Query` or `Db\Sql`.


- `join($join, ?string $alias = NULL, $onCondition = NULL)` (or `innerJoin(...)`/`leftJoin(...)`/`leftOuterJoin(...)`/`rightJoin(...)`/`rightOuterJoin(...)`/`fullJoin(...)`/`fullOuterJoin(...)`) - joins table or query. You must provide alias if you want to add more conditions to `ON`. `$join` can be simple string or other `Query` or `Db\Sql`. `$onCondition` can be simple `string` or other `Condition` or `Db\Sql`. `Db\Sql` can be used for some complex expression, where you need to use `?` and parameters. 


- `crossJoin($join, ?string $alias = NULL)` - defines cross-join. `$join` can be simple string or other `Query` or `Db\Sql`. There is no `ON` condition.


- `on(string $alias, $condition, ...$params)` - defines new `ON` condition for joins. More `ON` conditions for one join is connected with `AND`. If `$condition` is `string`, you can use `?` and parameters in `$params`. Otherwise `$condition` can be `Condition` or `Db\Sql`.


- `lateral(string $alias)` - make subquery lateral.


- `where($condition, ...$params)` (or `having(...)`) - defines `WHERE` or `HAVING` conditions. you can provide condition as a `string`. When `string` condition is used, you can add `$parameters`. When in the condition is no `?` and only one parameter is used, comparison is made between condition and parameter. If parameter is scalar, simple `=` is used, for an `array` is used `IN` operator, the same applies ale for `Query` (`Fluent\Query` or `Db\Sql`). And for `NULL` is used `IS` operator. This could be handy when you want to use more parameter types in one condition. For example, you can provide `int` and `=` will be use and if you provide `array<int>` - `IN` operator will be used and the query will be working for the both parameter types. More complex conditions can be written manually as a `string` with `?` for parameters. Or you can use `Condition` or `Db\Sql` as condition. In this case, `$params` must be blank. All `where()` or `having()` calls is connected with logic `AND`.


- `whereIf(bool $ifCondition, $condition, ...$params)` - the same as classics `where` method, but this condition is omitted when `$ifCondition` is `FALSE`.


- `whereAnd(array $conditions = []): Condition` (or `whereOr(...)` / `havingAnd(...)` / `havingOr()`) - with these methods, you can generate condition groups. Ale provided conditions are connected with logic `AND` for `whereAnd()` and `havingAnd()` and with logic `OR` for `whereOr()` and `havingOr()`. All these methods return `Condition` object (more about this later). `$conditions` items can be simple `string`, another `array` (this is a little bit magic - this works as `where()`/`having()` method - first item in this `array` is conditions and next items are parameters), `Condition` or `Db\Sql`. 


- `groupBy(string ...$columns)` - generates `GROUP BY` statement, one or more `string` parameters must be provided.


- `orderBy(...$columns): Query` - generates `ORDER BY` statement, one or more parameters must be provided. Parameter can be simple `string`, another `Query` or `Db\Sql`.


- `limit(int $limit)` - generates `LIMIT` statement with `int` parameter.


- `offset(int $offset)` - generates `OFFSET` statement with `int` parameter.


- `union($query)` (or `` / `` / ``) - connects two queries with `UNION`, `UNION ALL`, `INTERSECT` or `EXCEPT`. Query can be simple `string,` another `Query` or `Db\Sql`.


- `insert(?string $into = NULL, string $alias = NULL, ?array $columns = [])` - sets query as `INSERT`. When the main table is not provided yet, you can set it or rewrite it with the `$into` parameter. If you want use `INSERT ... SELECT ...` you can provide column names in `$columns` parameter (only if column names for INSERT and SELECT differs).


- `values(array $data)` - sets data for insertion. Key is column name and value is inserted value. Value can be scalar or `Db\Sql`. Method can be called multiple times and provided data is merged.


- `rows(array $rows)` - this method can be used to insert multiple rows in one query. `$rows` is an `array` of arrays. Each array is one row (the same as for the `values()` method). All rows must have the same columns. Method can be called multiple and all rows are merged.


- `onConflict($columnsOrConstraint = NULL, $where = NULL)` - this method can start `ON CONFLICT` statement for `INSERT`. When `array` is used as the `$columnsOrConstraint`, the list of columns is used, when `string` is used, constraint is used. This parameter can be completely omitted. Where condition `$where` can be defined only for the list of columns and can be simple `string` or other `Condition` or `Db\Sql`. `Db\Sql` can be used for some complex expression, where you need to use `?` and parameters.


- `doUpdate(array $set, $where = NULL)` - if conflict is detected, `UPDATE` is made instead of `INSERT`. Items od array `$set` can be defined in three ways. When only a `string` value is used (or key is an integer), this value is interpreted as `UPDATE SET value = EXCLUDED.value`. Only strings can be used without a key. When the array item has a `string` key, then `string` or `Db\Sql` value can be used, and now you must define a concrete statement to set (i.e., `['column' => 'EXCLUDED.column || source_table.column2']` is interpreted as `UPDATE SET column = EXCLUDED.column || source_table.column2`). `Db\Sql` can be used if you need to use parameters.


- `doNothing()` - if conflict is detected, nothing is done.


- `update(?string $table = NULL, ?string $alias = NULL)` - set query for update. If the main table is not set, you must set it or rewrite with the `$table` parameter. `$alias` can be provided, when you want to use `UPDATE ... FROM ...`.


- `set(array $data)` - sets data to update. Rules for the data are the same as for the `values()` method.


- `delete(?string $from = NULL, ?string $alias = NULL)` - set query for deletion. If the main table is not set, you must provide/rewrite it with `$from` parameter.


- `returning(array $returning)` - generates `RETURNING` statement for `INSERT`, `UPDATE` or `DELETE`. Syntax for `$returning` is the same as for the `select()` method.


- `merge(?string $into = NULL, ?string $alias = NULL)` - set query for merge. If the main table is not set, you must set it or rewrite with the `$into` parameter. `$alias` can be provided.


- `using($dataSource, ?string $alias = NULL, $onCondition = NULL)` - set a data source for a merge command. `$dataSource` can be simple string, `Db\Sql\Query` or `Fluent\Query`. `$onCondition` can be simple `string` or other `Condition` or `Db\Sql`. `Db\Sql` can be used for some complex expression, where you need to use `?` and parameters. On condition can be added or extended with the `on()` method.


- `whenMatched($then, $onCondition = NULL)` - add matched branch to a merge command. `$then` is simple string or `Db\Sql` and `$onCondition` can be simple `string` or other `Condition` or `Db\Sql`. `Db\Sql` can be used for some complex expression, where you need to use `?` and parameters.


- `whenNotMatched($then, $onCondition = NULL)` - add not matched branch to a merge command. `$then` is simple string or `Db\Sql` and `$onCondition` can be simple `string` or other `Condition` or `Db\Sql`. `Db\Sql` can be used for some complex expression, where you need to use `?` and parameters.


- `truncate(?string $table = NULL)` - truncates table. If the main table is not set, you must provide/rewrite it with the `$table` parameter.


- `prefix(string $queryPrefix/$querySuffix, ...$params)` (or `suffix(...)`) - with this, you can define universal query prefix or suffix. This is useful for actually not supported fluent syntax. With prefix, you can create CTE (Common Table Expression) queries. With suffix, you can create `SELECT ... FOR UPDATE` for example. Definition can be simple `string` or you can use `?` and parameters.


- `with(string $as, $query, ?string $suffix = NULL, bool $notMaterialized = FALSE)` - prepare CTE (Common Table Expression) query. `$as` is query alias/name, `$query` can be simple string, `Db\Sql\Query` or `Fluent\Query`, `$suffix` is optional definition like `SEARCH BREADTH FIRST BY ...` and `$notMaterialized` can set `WITH` branch as not materialized (materialized is default). `with()` can be called multiple times. When you use it, the query will always start with `WITH ...`.   

  
- `recursive()` - defines `WITH` query recursive.

If you want to create a copy of existing query, just use `clone`:

```php
$query = $connection->createQuery()->select(['nick'])->from('users');
$newQuery = clone $query;
```

`Query` internally saves own state for the `QueryBuilder`. You can check, if some internal state is already set with method `has(...)`. Use `Query::PARAM_*` constants as a parameter. You can also reset some settings with `reset(...)` method.

```php
$query = $connection->createQuery()->where('column', TRUE);

dump($query->has($query::PARAM_WHERE)); // (bool) TRUE

$query->reset($query::PARAM_WHERE);

dump($query->has($query::PARAM_WHERE)); // (bool) FALSE
```

Every table definition command (like `->table(...)`, `->from(...)`, joins, update table, ...) has table alias definition - it's optional, but for some places, you must define alias (also for joins, if you want to use another `on()` method, you must target `ON` condition to the concrete table with the table alias).

If you want to create an alias for a column in `SELECT`, use `string` key in `array` definition (the same for `returning()`):

```php
$query = $connection
  ->createQuery()
  ->select(['column1', 'alias' => 'column_with_alias']);

dump($query); // (Query) SELECT column1, column_with_alias AS \"alias\"
```

To almost every parameter (`select()`, `where()`, `having()`, `on()`, `orderBy()`, `returning()`, `from()`, `joins()`, unions, ...) you can pass `Db\Sql\Query` (or anything with `Db\Sql` interface) or other `Fluent\Query` object. At some places (`select()`, `from()`, joins), you must provide alias if you want to pass this objects.

```php
$query = $connection
  ->createQuery()
  ->select(['column'])
  ->from('table')
  ->limit(1);

$queryA = $connection
  ->createQuery()
  ->select(['c' => $query]);

dump($queryA); // (Query) SELECT (SELECT column FROM table LIMIT $1) AS \"c\" [Params: (array) [1]]

$queryB = $connection
  ->createQuery()
  ->select(['column'])
  ->from($query, 'c');

dump($queryB); // (Query) SELECT column FROM (SELECT column FROM table LIMIT $1) AS c [Params: (array) [1]]

$queryC = $connection
  ->createQuery()
  ->select(['column'])
  ->from('table', 't')
  ->join($query, 'c', 'c.id = t.id');

dump($queryC); // (Query) SELECT column FROM table AS t INNER JOIN (SELECT column FROM table LIMIT $1) AS c ON c.id = t.id [Params: (array) [1]]

$queryD = $connection
  ->createQuery()
  ->select(['column'])
  ->from('table', 't')
  ->where('id IN (?)', $query);

dump($queryD); // (Query) SELECT column FROM table AS t WHERE id IN (SELECT column FROM table LIMIT $1) [Params: (array) [1]]

$queryE = $connection
  ->createQuery()
  ->select(['column1', 'column2'])
  ->union($query);

dump($queryE); // (Query) (SELECT column1, column2) UNION (SELECT column FROM table LIMIT $1) [Params: (array) [1]]
```

### Complex conditions

Every condition (`WHERE`/`HAVING`/`ON`) are internally handled as the `Condition` object. With this, you can define really complex conditions connected with a logic `AND` or `OR`. One condition can be simple `string`, can have one argument with `=`/`IN`/`NULL`/`bool` detection or can have many arguments using `?` and parameters.

`Condition` is a list of conditions that are all connected with `AND` or `OR`. The magic is, that condition can be also another complex with different type (`AND` or `OR`).

`Condition` can be created with the static factory methods `Condition::createAnd(...)` or `Condition::createOr(...)`. The first argument can be an `array` with the condition list. New condition can be inserted with the `add(...)` method.

With methods `addAndCondtions(...)` or `addOrCondtions(...)` you can add new `Condition` object to the condition list and this new `Condition` object is returned. These `Condition` objects are connected into a tree structure (and can be connected also to the `Query` object). When you need to use simply fluent interface, you can use `parent()` method, that returns parent `Condition` or `query()` that returns connected `Query` object.

Method `getType()` returns `AND` or `OR` and `getConditions()` returns the list of all conditions. You will probably don't need these methods at all.

`Condition` also implements `ArrayAccess`, so you can add a new condition with simple `$condition[] = ...` syntax, get concrete condition with `$concreteCondition = $condition[...]` or remove one condition with the `unset($condition[...])`.

```php
$param = [1, 2];

$condition = Forrest79\PhPgSql\Fluent\Condition::createAnd([
  'column1 = 1',
  ['column2', TRUE],
  ['column3', $param],
  ['column4 < ? OR column5 != ?', 5, 10],
]);

$condition->add('column1', 81);
$condition->add('column4 < ? OR column5 != ?', 5, 10);
$condition[] = ['column1', 71]; // column1 = 1

$condition->addOrBranch([
    'column != TRUE'
])
  ->add('column2', TRUE)
  ->parent() // this return original condition object
    ->add('column3 < 1');
```

This defined condition can be used in `where($condition)` method, `having($condition)` method or as `on($condition)`/`join(..., $condition)` condition.

To create condition in a simpler way, there are methods `whereAnd()`/`whereOr()`/`havingAnd()`/`havingOr()` on the `Query` that return a new `Condition` connected to a query.

```php
$query = $connection
  ->createQuery()
  ->table('users')
  ->whereOr() // add new OR (return Condition object)
    ->add('column', 1) // this is add to OR
    ->add('column2', [2, 3]) // this is also add to OR
    ->addAndBranch() // this is also add to OR and can contains more ANDs
      ->add('column', $connection->createQuery()->select([1])) // this is add to AND
      ->add('column2 = ANY(?)', Forrest79\PhPgSql\Db\Sql\Query::create('SELECT 2')) // this is add to AND
      ->parent() // get original OR
    ->add('column3 IS NOT NULL') // and add to OR new condition
  ->query() // back to original query object
  ->select(['*']);

dump($query); // (Query) SELECT * FROM users WHERE (column = $1) OR (column2 IN ($2, $3)) OR ((column IN (SELECT 1)) AND (column2 = ANY(SELECT 2))) OR (column3 IS NOT NULL) [Params: (array) [1, 2, 3]]
```

To simplify a query definition, you can use a special version of `where()` method - the `whereIf()` method. This where is used in the query only if the first `bool` parameter is `TRUE`. For example, instead of this:

```php
$listItems = function (string|NULL $filterName) use ($connection): Forrest79\PhPgSql\Fluent\Query
{
  $query = $connection
    ->createQuery()
    ->table('users')
    ->select(['*']);
  
  if ($filterName !== NULL) {
    $query->where('name ILIKE ?', $filterName);
  }
  
  return $query;
};

dump($listItems(NULL)); // (Query) SELECT * FROM users
```

You can write this:


```php
$listItems = function (string|NULL $filterName) use ($connection): Forrest79\PhPgSql\Fluent\Query
{
  return $connection
    ->createQuery()
    ->table('users')
    ->select(['*'])
    ->whereIf($filterName !== NULL, 'name ILIKE ?', $filterName);
};

dump($listItems('forrest79')); // (Query) SELECT * FROM users WHERE name ILIKE $1 [Params: (array) ['forrest79']]
```

### Insert

You can insert a simple row:

```php
$query = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'nick' => 'James',
    'inserted_datetime' => Forrest79\PhPgSql\Db\Sql\Literal::create('now()'),
    'active' => TRUE,
    'age' => 37,
    'height_cm' => NULL,
    'phones' => Forrest79\PhPgSql\Db\Helper::createStringPgArray(['732123456', '736987654']),
  ]);
 
dump($query); // (Query) INSERT INTO users (nick, inserted_datetime, active, age, height_cm, phones) VALUES($1, now(), $2, $3, $4, $5) [Params: (array) ['James', 't', 37, (NULL), '{\"732123456\",\"736987654\"}']]

$result = $query->execute();

dump($result->getAffectedRows()); // (integer) 1

$insertedRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'nick' => 'Jimmy',
  ])
  ->getAffectedRows();

dump($insertedRows); // (integer) 1
```

Or you can use the returning statement:

```php
$insertedData = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'nick' => 'Jimmy',
  ])
  ->returning(['id', 'nick'])
  ->fetch();

dump($insertedData); // (Row) ['id' => 6, 'nick' => 'Jimmy']
```

You can use multi-insert too:

```php
$query = $connection
  ->createQuery()
  ->insert('users')
  ->rows([
    ['nick' => 'Luis'],
    ['nick' => 'Gilbert'],
    ['nick' => 'Zoey'],
  ]);

dump($query); // (Query) INSERT INTO users (nick) VALUES($1), ($2), ($3) [Params: (array) ['Luis', 'Gilbert', 'Zoey']]

$insertedRows = $query->getAffectedRows();

dump($insertedRows); // (integer) 3
```

Here are column names detected from the first row. You can also pass the columns as a second parameter in `insert()` method:

```php
$insertedRows = $connection
  ->createQuery()
  ->insert('users', columns: ['nick', 'age'])
  ->rows([
    ['Luis', 31],
    ['Gilbert', 18],
    ['Zoey', 28],
  ])
  ->getAffectedRows();

dump($insertedRows); // (integer) 3
```

And of course, you can use `INSERT` - `SELECT`:

```php
$query = $connection
  ->createQuery()
  ->insert('users', columns: ['nick'])
  ->select(['name' || '\'_\'' || 'age'])
  ->from('departments')
  ->where('id', [1, 2]);

dump($query); // (Query) INSERT INTO users (nick) SELECT TRUE FROM departments WHERE id IN ($1, $2) [Params: (array) [1, 2]]

$insertedRows = $query->getAffectedRows();

dump($insertedRows); // (integer) 2
```

And if you're using the same names for columns in `INSERT` and `SELECT`, you can call insert without the column list, and it will be detected from the `SELECT` columns.

```php
$insertedRows = $connection
  ->createQuery()
  ->insert('users')
  ->select(['nick'])
  ->from('users', 'u2')
  ->where('id', [1, 2])
  ->getAffectedRows();

dump($insertedRows); // (integer) 2
```

You have to use alias `u2` when you're inserting to the same table as selecting from.

#### UPSERT

If you want to write an UPSERT command, use `onConflict()` method with `doUpdate()` or `doNothing()`.

Simple use - check column `id` for conflict update `nick` is conflict is detected.

```php
$insertedOrUpdatedRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '20',
    'nick' => 'Jimmy',
  ])
  ->onConflict(['id'])
  ->doUpdate(['nick'])
  ->getAffectedRows();

dump($insertedOrUpdatedRows); // (integer) 1
```

The same with `WHERE` statement on conflicted columns.

```php
$insertedOrUpdatedWithWhereOnConflictRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '20',
    'nick' => 'James',
  ])
  ->onConflict(['id'], Forrest79\PhPgSql\Fluent\Condition::createAnd()->add('users.nick != ?', 'James'))
  ->doUpdate(['nick'])
  ->getAffectedRows();

dump($insertedOrUpdatedWithWhereOnConflictRows); // (integer) 1
```
The same with `WHERE` statement on `UPDATE SET`.

```php
$insertedOrUpdatedWithWhereOnUpdateRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '20',
    'nick' => 'Margaret',
  ])
  ->onConflict(['id'])
  ->doUpdate(['nick'], Forrest79\PhPgSql\Fluent\Condition::createAnd()->add('users.nick != ?', 'Margaret'))
  ->getAffectedRows();

dump($insertedOrUpdatedWithWhereOnUpdateRows); // (integer) 1
```

And to ignore conflicting inserts:

```php
$insertedOrUpdatedDoNothingRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '1',
    'nick' => 'Steve',
  ])
  ->onConflict()
  ->doNothing()
  ->getAffectedRows();

dump($insertedOrUpdatedDoNothingRows); // (integer) 0
```

To use constraint name in `ON CONFLICT`:

```php
$insertedOrUpdatedWithConstraintRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '20',
    'nick' => 'Jimmy',
  ])
  ->onConflict('users_pkey')
  ->doUpdate(['nick'])
  ->getAffectedRows();

dump($insertedOrUpdatedWithConstraintRows); // (integer) 1
```

And the last to use manually `SET` with string (here we can use alias for `INTO` table) or also with parameters:

```php
$insertedOrUpdatedRows = $connection
  ->createQuery()
  ->insert('users', 'u')
  ->values([
    'id' => '20',
    'nick' => 'Jimmy',
  ])
  ->onConflict(['id'])
  ->doUpdate(['nick' => 'EXCLUDED.nick || u.id'])
  ->getAffectedRows();

dump($insertedOrUpdatedRows); // (integer) 1

$insertedOrUpdatedRows = $connection
  ->createQuery()
  ->insert('users')
  ->values([
    'id' => '20',
    'nick' => 'Jimmy',
  ])
  ->onConflict(['id'])
  ->doUpdate(['nick' => Forrest79\PhPgSql\Db\Sql\Expression::create('EXCLUDED.nick || ?', 'updated')])
  ->getAffectedRows();

dump($insertedOrUpdatedRows); // (integer) 1
```

### Update

You can use simple update:

```php
$updatedRows = $connection
  ->createQuery()
  ->update('users')
  ->set([
    'nick' => 'Thomas',
  ])
  ->where('id', 10)
  ->getAffectedRows();

dump($updatedRows); // (integer) 0
```

There is no row with the `id = 10`, so `0` rows was updated.

Or complex with from (and joins, ...):

```php
$query = $connection
  ->createQuery()
  ->update('users', 'u')
  ->set([
    'nick' => Forrest79\PhPgSql\Db\Sql\Literal::create('u.nick || \' - \' || d.name'),
    'age' => NULL,
  ])
  ->from('departments', 'd');

dump($query); // (Query) UPDATE users AS u SET nick = u.nick || ' - ' || d.name, age = $1 FROM departments AS d [Params: (array) [(NULL)]]

$result = $query->execute();

dump($result->getAffectedRows()); // (integer) 5
```

### Delete

Simple delete with a condition:

```php
$deleteRows = $connection
  ->createQuery()
  ->delete('users')
  ->where('id', 1)
  ->getAffectedRows();

dump($deleteRows); // (integer) 1
```

### Merge

Official docs: https://www.postgresql.org/docs/current/sql-merge.html

`MERGE` command was added in the PostgreSQL v15. You can use it to conditionally insert, update, or delete rows of a table.

Simple use can look like:

```php
$query = $connection
  ->createQuery()
  ->merge('customer_account', 'ca')
  ->using('recent_transactions', 't', 't.customer_id = ca.customer_id')
  ->whenMatched('UPDATE SET balance = balance + transaction_value')
  ->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)');

dump($query); // (Query) MERGE INTO customer_account AS ca USING recent_transactions AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)
```

The `ON` condition can be used with the `on()` method:

```php
$query = $connection
  ->createQuery()
  ->merge('customer_account', 'ca')
  ->using('recent_transactions', 't')
  ->on('t', 't.customer_id = ca.customer_id')
  ->whenMatched('UPDATE SET balance = balance + transaction_value')
  ->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)');

dump($query); // (Query) MERGE INTO customer_account AS ca USING recent_transactions AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)
```

The `WHEN (NOT) MATCHED` branches can have conditions:

```php
$query = $connection
  ->createQuery()
  ->merge('wines', 'w')
  ->using('wine_stock_changes', 's', 's.winename = w.winename')
  ->whenNotMatched('INSERT VALUES(s.winename, s.stock_delta)', 's.stock_delta > 0')
  ->whenMatched('UPDATE SET stock = w.stock + s.stock_delta', Forrest79\PhPgSql\Fluent\Condition::createAnd()->add('w.stock + s.stock_delta > ?', 0))
  ->whenMatched('DELETE');

dump($query); // (Query) MERGE INTO wines AS w USING wine_stock_changes AS s ON s.winename = w.winename WHEN NOT MATCHED AND s.stock_delta > 0 THEN INSERT VALUES(s.winename, s.stock_delta) WHEN MATCHED AND w.stock + s.stock_delta > $1 THEN UPDATE SET stock = w.stock + s.stock_delta WHEN MATCHED THEN DELETE [Params: (array) [0]]
```

Also `DO NOTHING` clause can be used:

```php
$query = $connection
  ->createQuery()
  ->merge('wines', 'w')
  ->using('wine_stock_changes', 's', 's.winename = w.winename')
  ->whenNotMatched('INSERT VALUES(s.winename, s.stock_delta)')
  ->whenMatched('DO NOTHING');

dump($query); // (Query) MERGE INTO wines AS w USING wine_stock_changes AS s ON s.winename = w.winename WHEN NOT MATCHED THEN INSERT VALUES(s.winename, s.stock_delta) WHEN MATCHED THEN DO NOTHING
```

And since PostgreSQL v17 there is also possibility to use `RETURNING`:

```php
$query = $connection
  ->createQuery()
  ->merge('customer_account', 'ca')
  ->using('recent_transactions', 't', 't.customer_id = ca.customer_id')
  ->whenMatched('UPDATE SET balance = balance + transaction_value')
  ->whenNotMatched('INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value)')
  ->returning(['merge_action()', 'ca.*']);

dump($query); // (Query) MERGE INTO customer_account AS ca USING recent_transactions AS t ON t.customer_id = ca.customer_id WHEN MATCHED THEN UPDATE SET balance = balance + transaction_value WHEN NOT MATCHED THEN INSERT (customer_id, balance) VALUES (t.customer_id, t.transaction_value) RETURNING merge_action(), ca.*
```

#### Upsert

The `MERGE` command can be used for simply upsert (perform UPDATE and if record not exists yet perform INSERT). The query could look like this:

```sql
MERGE INTO users AS u
  USING (VALUES ('Bob', FALSE)) AS source (nick, active) ON u.nick = source.nick
  WHEN MATCHED THEN
    UPDATE SET active = source.active
  WHEN NOT MATCHED THEN
    INSERT (nick, active) VALUES (source.nick, source.active);
``` 

Unfortunately, this can't be used simply with the parameters:

```sql
MERGE INTO users AS u
  USING (VALUES (?, ?)) AS source (nick, active) ON u.nick = source.nick
  WHEN MATCHED THEN
    UPDATE SET active = source.active
  WHEN NOT MATCHED THEN
    INSERT (nick, active) VALUES (source.nick, source.active);
```

Because DB needs to know the parameter types and all parameters are treated as text. You must use a concrete cast like this:

```sql
MERGE INTO users AS u
  USING (VALUES (?, ?::boolean)) AS source (nick, active) ON u.nick = source.nick
  WHEN MATCHED THEN
    UPDATE SET active = source.active
  WHEN NOT MATCHED THEN
    INSERT (nick, active) VALUES (source.nick, source.active);
```

For a query like this, it's not a problem. But when you want to prepare a common method for more tables and parameters, you must use a little trick.

```sql
MERGE INTO users AS u
  USING (SELECT 1) AS x ON u.nick = $1
  WHEN MATCHED THEN
    UPDATE SET active = $2
  WHEN NOT MATCHED THEN
    INSERT (nick, active) VALUES ($1, $2);
```
And this is how this could be prepared with the fluent interface:

```php
$updateRow = $connection
  ->createQuery()
  ->merge('users', 'u')
  ->using('(SELECT 1)', 'x', 'u.nick = $1')
  ->whenMatched('UPDATE SET active = $2')
  ->whenNotMatched(Forrest79\PhPgSql\Db\Sql\Expression::create('INSERT (nick, active) VALUES ($1, $2)', 'Bob', 'f'))
  ->getAffectedRows();

dump($updateRow); // (integer) 1

$updatedRows = $connection->query('SELECT nick, active FROM users WHERE nick = ?', 'Bob')->fetchAll();

table($updatedRows);
/**
---------------------------------
| nick           | active       |
|===============================|
| (string) 'Bob' | (bool) FALSE |
---------------------------------
*/

$insertRow = $connection
  ->createQuery()
  ->merge('users', 'u')
  ->using('(SELECT 1)', 'x', 'u.nick = $1')
  ->whenMatched('UPDATE SET active = $2')
  ->whenNotMatched(Forrest79\PhPgSql\Db\Sql\Expression::create('INSERT (nick, active) VALUES ($1, $2)', 'Margaret', 't'))
  ->getAffectedRows();

dump($updateRow); // (integer) 1

$insertedRows = $connection->query('SELECT nick, active FROM users WHERE nick = ?', 'Margaret')->fetchAll();

table($insertedRows);
/**
-------------------------------------
| nick                | active      |
|===================================|
| (string) 'Margaret' | (bool) TRUE |
-------------------------------------
*/
```

> IMPORTANT: with this trick, when `$1`, `$2`, ... is used instead of `?`, `?`, ... we must use bool parameters as `t` and `f`. Automatic bool parameters replacing remove `?` from the query and bool parameter from the parameter list and put string `'TRUE'` or `'FALSE'` right into the query. When `$1` is used, bool parameter is still removed from the list, but the query is untouched, so there will be fewer parameters than `$1`, `$2`, ... in the query. 

### Truncate

Just with table name:

```php
$connection
  ->createQuery()
  ->truncate('user_departments')
  ->execute();

$query = $connection
  ->createQuery()
  ->table('departments')
  ->truncate()
  ->suffix('CASCADE'); // generate `TRUNCATE departments CASCADE`

dump($query); // (Query) TRUNCATE departments CASCADE

$query->execute();
```

### With (Common Table Expression queries)

Official docs: https://www.postgresql.org/docs/current/queries-with.html

You can use `WITH` with a simple string query, or defined it with `Db\Sql\Query` or `Fluen\Query` queries:

```php
$query = $connection
  ->createQuery()
  ->with('active_users', 'SELECT id, nick, age, height_cm FROM users WHERE active = TRUE')
  ->with('active_departments', Forrest79\PhPgSql\Db\Sql\Query::create('SELECT id FROM departments WHERE active = ?', TRUE))
  ->select(['au.id', 'au.nick', 'au.age', 'au.height_cm'])
  ->from('active_users', 'au')
  ->join('user_departments', 'ud', 'ud.department_id = au.id')
  ->where('ud.department_id IN (?)', Forrest79\PhPgSql\Db\Sql\Query::create('SELECT id FROM active_departments'));

dump($query); // (Query) WITH active_users AS (SELECT id, nick, age, height_cm FROM users WHERE active = TRUE), active_departments AS (SELECT id FROM departments WHERE active = $1) SELECT au.id, au.nick, au.age, au.height_cm FROM active_users AS au INNER JOIN user_departments AS ud ON ud.department_id = au.id WHERE ud.department_id IN (SELECT id FROM active_departments) [Params: (array) ['t']]

$query->execute();
```

You can define `WITH` query recursive:

```php
$query = $connection
  ->createQuery()
  ->with('t(n)', 'VALUES (1) UNION ALL SELECT n + 1 FROM t WHERE n < 100')
  ->recursive()
  ->select(['sum(n)'])
  ->from('t');

dump($query); // (Query) WITH RECURSIVE t(n) AS (VALUES (1) UNION ALL SELECT n + 1 FROM t WHERE n < 100) SELECT sum(n) FROM t

$query->execute();
```

Or with some special suffix definition:

```php
$query = $connection
  ->createQuery()
  ->with(
    'search_tree(id, link, data)',
    'SELECT t.id, t.link, t.data FROM tree AS t UNION ALL SELECT t.id, t.link, t.data FROM tree AS t, search_tree AS st WHERE t.id = st.link', 
    'SEARCH BREADTH FIRST BY id SET ordercol'
  )
  ->select(['*'])
  ->from('search_tree')
  ->orderBy('ordercol');

dump($query); // (Query) WITH search_tree(id, link, data) AS (SELECT t.id, t.link, t.data FROM tree AS t UNION ALL SELECT t.id, t.link, t.data FROM tree AS t, search_tree AS st WHERE t.id = st.link) SEARCH BREADTH FIRST BY id SET ordercol SELECT * FROM search_tree ORDER BY ordercol
```

Or not materialized:

```php
$query = $connection
  ->createQuery()
  ->with('w', 'SELECT * FROM big_table', NULL, TRUE)
  ->select(['*'])
  ->from('w', 'w1')
  ->join('w', 'w2', 'w1.key = w2.ref')
  ->where('w2.key', 123);

dump($query); // (Query) WITH w AS NOT MATERIALIZED (SELECT * FROM big_table) SELECT * FROM w AS w1 INNER JOIN w AS w2 ON w1.key = w2.ref WHERE w2.key = $1 [Params: (array) [123]]
```

Query after `WITH` can be `SELECT`, `INSERT`, `UPDATE` or `DELETE`.

## Fetching data from DB

On `QueryExecute`, you can use all fetch functions as on the `Db\Result`. All `fetch*()` methods call `execute()` that run query in DB and returns the `Db\Result` object. The `execute()` method can be used everytime, but it's handy mostly for queries returning no data.

> You can pass a query object to `foreach` without calling `execute()` or another fetch function. This is good, because just one rows iteration is made (`fetchAll()`, `fetchPairs()` and `fetchAssoc()` iterate all rows in background before return an array). If you want to iterate rows just once and run query in DB earlier than in `foreach`, just call `execute()` whenever you want and pass query object or returned result object to `foreach`.
> We can get the same behavior using `fetchIterator`.

You can update your query till `execute()` is call, after that, no updates on query is available, you can only execute this query again by calling `reexecute()`:

```php
$query = $connection
  ->createQuery()
  ->select(['nick'])
  ->from('users')
  ->where('id', 1);
 
$userNick = $query->fetchSingle();

dump($userNick); // (string) 'Bob'

$connection
  ->createQuery()
  ->update('users')
  ->set(['nick' => 'Thomas'])
  ->where('id', 1)
  ->execute();

$updatedUserNick = $query->reexecute()->fetchSingle();

dump($updatedUserNick); // (string) 'Thomas'
```

If you clone an already executed query, copy is cloned with the reset result, so you can still update the query and then execute it.

You can also run the async query with the `asyncExecute()` method.

```php
$asyncQuery = $connection
  ->createQuery()
  ->select(['nick'])
  ->from('users')
  ->where('id', 1)
  ->asyncExecute();

// do some logic here

$result = $asyncQuery->getNextResult();
 
$userNick = $result->fetchSingle();

dump($userNick); // (string) 'Bob'
```

### Fetch mutators

You can set fetch mutators for the `Result` object right on the `QueryExecute`. There are the same two methods: `QueryExecute::setRowFetchMutator(callable)` and `QueryExecute::setColumnsFetchMutator(array<string, callable>)`.
Already set fetch mutators are keep also for re-execution the query.  

## Extending

You can extend generating SQL queries with your own logic (for example, you can replace some placeholder with a value from your application service). Extends `QueryBuilder` and overwrites method `prepareSqlQuery(...)` that get generated string SQL and all params, so you can update this string or parameters before the `Db\Sql\Query` is returned.

To use your new `QueryBuilder` automatically from the `Connection`, use `Connection::setQueryBuilder()` method or overwrite the `Connection::getQueryBuilder()` method.

The second you can extend is the `Query` or `QueryExecute` object. For example, we want to add method `exists()` that will provide something like this `SELECT EXISTS (SELECT TRUE FROM ... WHERE ...)`:

```php
class Query extends Forrest79\PhPgSql\Fluent\QueryExecute
{
  private $connection;

  public function __construct(Forrest79\PhPgSql\Fluent\QueryBuilder $queryBuilder, Forrest79\PhPgSql\Db\Connection $connection)
  {
    $this->connection = $connection;
    parent::__construct($queryBuilder, $connection);
  }

  public function exists(): bool
  {
    return (bool) $this->connection
      ->query('SELECT EXISTS (?)', $this->select(['TRUE']))
      ->fetchSingle();
  }
}

$query1 = (new Query(new Forrest79\PhPgSql\Fluent\QueryBuilder(), $connection))->from('users');
$query2 = clone $query1;

dump($query1->where('id', 1)->exists()); // (bool) TRUE
dump($query2->where('id', 10)->exists()); // (bool) FALSE
```

Of course, you want to use your own query right from the connection. So overwrite `Connection::createQuery()` method and return instance of your own query here.

## Query examples

- `SELECT ... FOR UPDATE`

```php
$query = $connection
  ->createQuery()
  ->select(['nick'])
  ->from('users')
  ->where('id', 1)
  ->suffix('FOR UPDATE');

dump($query); // (Query) SELECT nick FROM users WHERE id = $1 FOR UPDATE [Params: (array) [1]]

$query->execute();
```

- CTE query

```php
$innerQuery = $connection
  ->createQuery()
  ->select(['id', 'nick'])
  ->from('users');

$query = $connection
  ->createQuery()
  ->prefix('WITH usr AS (?)', $innerQuery)
  ->select(['nick'])
  ->from('usr')
  ->where('id = 1');

dump($query); // (Query) WITH usr AS (SELECT id, nick FROM users) SELECT nick FROM usr WHERE id = 1

$query->execute();
```

- Using expression

```php
$query = $connection
  ->createQuery()
  ->select(['is_old' => Forrest79\PhPgSql\Db\Sql\Expression::create('age > ?', 37)])
  ->from('users')
  ->orderBy(Forrest79\PhPgSql\Db\Sql\Expression::create('CASE WHEN age > ? THEN 1 ELSE 2 END', 36));

dump($query); // (Query) SELECT (age > $1) AS \"is_old\" FROM users ORDER BY CASE WHEN age > $2 THEN 1 ELSE 2 END [Params: (array) [37, 36]]

$query->execute();
```

```php
$query = $connection
  ->createQuery()
  ->update('users')
  ->set([
    'nick' => Forrest79\PhPgSql\Db\Sql\Expression::create('CASE WHEN age > ? THEN \'old \' || nick ELSE \'young \' || nick END', 36),
  ]);

dump($query); // (Query) UPDATE users SET nick = CASE WHEN age > $1 THEN 'old ' || nick ELSE 'young ' || nick END [Params: (array) [36]]

$query->execute();
```
