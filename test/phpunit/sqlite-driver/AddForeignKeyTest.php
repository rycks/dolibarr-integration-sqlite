<?php
/**
 * Regression tests for the ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY
 * emulation of DoliDBSqlite3::query().
 *
 * SQLite has no native "ALTER TABLE ADD FOREIGN KEY", so the driver rebuilds
 * the table (rename -> create with the constraint -> copy rows -> drop the
 * temporary table). Indexes belong to the table in SQLite: they follow the
 * renamed table and die with it, so the rebuild used to wipe every index of
 * the rebuilt table, UNIQUE ones included. A module whose sql/update_NNN_NN.sql
 * adds a foreign key after its indexes were created (llx_*.key.sql runs first,
 * update_* files come later in the alphabetical order used by _load_tables())
 * silently lost its uniqueness guarantees.
 */

use PHPUnit\Framework\TestCase;

class AddForeignKeyTest extends TestCase
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

		$this->sqlOk("CREATE TABLE llx_test_parent(rowid INTEGER AUTO_INCREMENT PRIMARY KEY, label VARCHAR(64)) ENGINE=innodb;");
		$this->sqlOk("CREATE TABLE llx_test_child(rowid INTEGER AUTO_INCREMENT PRIMARY KEY, entity INTEGER DEFAULT 1 NOT NULL, fk_parent INTEGER NOT NULL, client_uuid VARCHAR(36), status TINYINT DEFAULT 0 NOT NULL) ENGINE=innodb;");
		$this->sqlOk("ALTER TABLE llx_test_child ADD INDEX idx_test_child_parent (entity, fk_parent, status);");
		$this->sqlOk("ALTER TABLE llx_test_child ADD UNIQUE INDEX uk_test_child_client_uuid (entity, client_uuid);");

		$this->sqlOk("INSERT INTO llx_test_parent (rowid, label) VALUES (1, 'p1');");
		$this->sqlOk("INSERT INTO llx_test_child (rowid, entity, fk_parent, client_uuid, status) VALUES (1, 1, 1, 'uuid-1', 0);");
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

	/**
	 * Helper: run a statement expected to succeed.
	 *
	 * @param string $sql Query to run
	 * @return void
	 */
	private function sqlOk($sql)
	{
		$res = $this->db->query($sql);
		$this->assertNotFalse($res, 'Query failed: ' . $sql . ' :: ' . (string) $this->db->lasterror());
	}

	/**
	 * Helper: list the explicit indexes (those with a DDL) of a table.
	 *
	 * @param string $table Table name
	 * @return string[]     Index names, sorted
	 */
	private function indexesOf($table)
	{
		$res = $this->db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='" . $table . "' AND sql IS NOT NULL ORDER BY name");
		$this->assertNotFalse($res, 'Cannot list indexes: ' . (string) $this->db->lasterror());
		$names = array();
		while ($row = $this->db->fetch_row($res)) {
			$names[] = $row[0];
		}
		return $names;
	}

	/**
	 * Helper: read back the CREATE TABLE statement of a table.
	 *
	 * @param string $table Table name
	 * @return string       Stored DDL
	 */
	private function ddlOf($table)
	{
		$res = $this->db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='" . $table . "'");
		$this->assertNotFalse($res, 'Cannot read table DDL: ' . (string) $this->db->lasterror());
		$row = $this->db->fetch_row($res);
		return $row ? (string) $row[0] : '';
	}

	public function testBaselineIndexesExistBeforeAnyConstraint()
	{
		$this->assertSame(
			array('idx_test_child_parent', 'uk_test_child_client_uuid'),
			$this->indexesOf('llx_test_child')
		);
	}

	public function testIndexesSurviveAddForeignKey()
	{
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_parent FOREIGN KEY (fk_parent) REFERENCES llx_test_parent (rowid);");

		$this->assertSame(
			array('idx_test_child_parent', 'uk_test_child_client_uuid'),
			$this->indexesOf('llx_test_child'),
			'Adding a foreign key must not drop the indexes of the rebuilt table'
		);
		$this->assertStringContainsString('CONSTRAINT fk_test_child_parent FOREIGN KEY', $this->ddlOf('llx_test_child'));
	}

	public function testUniqueIndexStillEnforcedAfterAddForeignKey()
	{
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_parent FOREIGN KEY (fk_parent) REFERENCES llx_test_parent (rowid);");

		// The driver lets SQLite3 emit its own warning on the rejected INSERT,
		// silenced here because the rejection is exactly what is expected.
		$res = @$this->db->query("INSERT INTO llx_test_child (rowid, entity, fk_parent, client_uuid, status) VALUES (2, 1, 1, 'uuid-1', 0);");
		$this->assertFalse($res, 'The UNIQUE index must still refuse a duplicate (entity, client_uuid)');
	}

	public function testRowsAreKeptAcrossTheRebuild()
	{
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_parent FOREIGN KEY (fk_parent) REFERENCES llx_test_parent (rowid);");

		$res = $this->db->query("SELECT client_uuid FROM llx_test_child WHERE rowid = 1");
		$this->assertNotFalse($res, (string) $this->db->lasterror());
		$row = $this->db->fetch_row($res);
		$this->assertSame('uuid-1', $row[0]);
	}

	public function testIndexesSurviveAddForeignKeyWithCascadeClause()
	{
		// The ON DELETE / ON UPDATE clauses are dropped by the emulation (SQLite
		// does not enforce foreign keys unless PRAGMA foreign_keys is ON), but
		// they must not prevent the indexes from being restored.
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_parent FOREIGN KEY (fk_parent) REFERENCES llx_test_parent (rowid) ON DELETE CASCADE ON UPDATE CASCADE;");

		$this->assertSame(
			array('idx_test_child_parent', 'uk_test_child_client_uuid'),
			$this->indexesOf('llx_test_child')
		);
	}

	public function testIndexesSurviveTwoSuccessiveForeignKeys()
	{
		// Real module case: llx_*.key.sql adds a first constraint, then an
		// update_NNN_NN.sql re-adds both, so the table gets rebuilt several times.
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_parent FOREIGN KEY (fk_parent) REFERENCES llx_test_parent (rowid);");
		$this->sqlOk("ALTER TABLE llx_test_child ADD CONSTRAINT fk_test_child_entity FOREIGN KEY (entity) REFERENCES llx_test_parent (rowid);");

		$this->assertSame(
			array('idx_test_child_parent', 'uk_test_child_client_uuid'),
			$this->indexesOf('llx_test_child')
		);
		$ddl = $this->ddlOf('llx_test_child');
		$this->assertStringContainsString('CONSTRAINT fk_test_child_parent FOREIGN KEY', $ddl);
		$this->assertStringContainsString('CONSTRAINT fk_test_child_entity FOREIGN KEY', $ddl);
	}
}
