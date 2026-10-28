<?php
/**
 * ORDER BY on a name of the select list, resolved the MySQL way: the core
 * query of the Events tab of an object (show_actions_done()) selects a.id from
 * llx_actioncomm joined with llx_c_actioncomm, which also has an id column,
 * and sorts on "id". MySQL sorts on a.id; SQLite reported the name ambiguous.
 */

use PHPUnit\Framework\TestCase;

class OrderByOutputColumnTest extends TestCase
{
	/** @var DoliDBSqlite3|null */
	private $db;

	/** @var string */
	private $dbname = '';

	protected function setUp(): void
	{
		$this->dbname = 'unittest_' . getmypid() . '_' . substr(md5(uniqid('', true)), 0, 8);
		$this->db = new DoliDBSqlite3('sqlite3', 'localhost', 'test', 'test', $this->dbname);
		$this->assertTrue($this->db->connected, 'Driver should be connected');
	}

	protected function tearDown(): void
	{
		if ($this->db && $this->db->connected) {
			$this->db->close();
		}
		$file = ($GLOBALS['main_data_dir'] ?? sys_get_temp_dir()) . '/database_' . $this->dbname . '.sdb';
		if (is_file($file)) {
			@unlink($file);
		}
		$this->db = null;
	}

	private function convert($sql)
	{
		return DoliDBSqlite3::convertSQLFromMysql($sql, 'auto');
	}

	public function testBareNameOfAQualifiedSelectColumnIsQualified()
	{
		$this->assertSame(
			"SELECT a.id, a.label as label, a.datep as dp FROM t as a, c as o WHERE a.x = o.y ORDER BY dp DESC, a.id DESC LIMIT 10",
			$this->convert("SELECT a.id, a.label as label, a.datep as dp FROM t as a, c as o WHERE a.x = o.y ORDER BY dp DESC, id DESC LIMIT 10")
		);
	}

	public function testExplicitAliasIsLeftAlone()
	{
		$sql = "SELECT a.id as id, c.id as cid FROM t as a, c WHERE a.c = c.id ORDER BY id DESC";
		$this->assertSame($sql, $this->convert($sql));
	}

	public function testNameCarriedByTwoSelectColumnsIsLeftAlone()
	{
		$sql = "SELECT a.id, c.id FROM t as a, c WHERE a.c = c.id ORDER BY id";
		$this->assertSame($sql, $this->convert($sql));
	}

	public function testSubqueriesAndStringsAreNotRewritten()
	{
		$sql = "SELECT a.id FROM t as a WHERE a.label = 'x ORDER BY id' AND a.k IN (SELECT b.id FROM b ORDER BY id) ORDER BY a.id";
		$this->assertSame($sql, $this->convert($sql));
	}

	public function testRequestWithoutOrderByIsUnchanged()
	{
		$sql = "SELECT a.id, c.id FROM t as a, c WHERE a.c = c.id";
		$this->assertSame($sql, $this->convert($sql));
	}

	public function testAmbiguousSortRunsLikeOnMysql()
	{
		$this->assertNotFalse($this->db->query("CREATE TABLE llx_t (id INTEGER PRIMARY KEY, label TEXT, fk_c INTEGER)"));
		$this->assertNotFalse($this->db->query("CREATE TABLE llx_c (id INTEGER PRIMARY KEY, code TEXT)"));
		$this->db->query("INSERT INTO llx_c (id, code) VALUES (1, 'x')");
		foreach (array(1, 2, 3) as $id) {
			$this->db->query("INSERT INTO llx_t (id, label, fk_c) VALUES (" . $id . ", 'row " . $id . "', 1)");
		}

		$res = $this->db->query("SELECT a.id, a.label as label, c.code FROM llx_t as a LEFT JOIN llx_c as c ON a.fk_c = c.id ORDER BY id DESC");
		$this->assertNotFalse($res, 'Query failed: ' . $this->db->lasterror());
		$ids = array();
		while ($row = $this->db->fetch_object($res)) {
			$ids[] = (int) $row->id;
		}
		$this->assertSame(array(3, 2, 1), $ids);
	}
}
