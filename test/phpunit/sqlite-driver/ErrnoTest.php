<?php
/**
 * Generic error codes of the driver, the ones the core tolerates on purpose:
 * ExtraFields::addExtraField() accepts DB_ERROR_COLUMN_ALREADY_EXISTS (MySQL
 * 1060) for the column and DB_ERROR_RECORD_ALREADY_EXISTS (MySQL 1062) for the
 * declaration row of an extra field still there, so that a module can be
 * activated again after its deactivation.
 */

use PHPUnit\Framework\TestCase;

class ErrnoTest extends TestCase
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

	public function testAddingAnExistingColumnReportsColumnAlreadyExists()
	{
		$this->assertNotFalse($this->db->query("CREATE TABLE llx_errno_probe (rowid integer)"));
		$this->assertNotFalse($this->db->query("ALTER TABLE llx_errno_probe ADD COLUMN label varchar(32)"));

		$this->assertFalse(@$this->db->query("ALTER TABLE llx_errno_probe ADD COLUMN label varchar(32)"));
		$this->assertSame('DB_ERROR_COLUMN_ALREADY_EXISTS', $this->db->lasterrno());
	}

	public function testADuplicateUniqueKeyReportsRecordAlreadyExists()
	{
		$this->assertNotFalse($this->db->query("CREATE TABLE llx_errno_probe (rowid integer, name varchar(32), entity integer)"));
		$this->assertNotFalse($this->db->query("CREATE UNIQUE INDEX uk_errno_probe ON llx_errno_probe (name, entity)"));
		$this->assertNotFalse($this->db->query("INSERT INTO llx_errno_probe (rowid, name, entity) VALUES (1, 'label', 1)"));

		$this->assertFalse(@$this->db->query("INSERT INTO llx_errno_probe (rowid, name, entity) VALUES (2, 'label', 1)"));
		$this->assertSame('DB_ERROR_RECORD_ALREADY_EXISTS', $this->db->lasterrno());
	}

	public function testAMissingMandatoryValueIsNotADuplicate()
	{
		$this->assertNotFalse($this->db->query("CREATE TABLE llx_errno_probe (rowid integer, name varchar(32) NOT NULL)"));

		$this->assertFalse(@$this->db->query("INSERT INTO llx_errno_probe (rowid) VALUES (1)"));
		$this->assertNotSame('DB_ERROR_RECORD_ALREADY_EXISTS', $this->db->lasterrno());
	}

	public function testAnotherErrorKeepsItsGenericCode()
	{
		$this->assertFalse(@$this->db->query("ALTER TABLE llx_errno_missing ADD COLUMN label varchar(32)"));
		$this->assertNotSame('DB_ERROR_COLUMN_ALREADY_EXISTS', $this->db->lasterrno());
	}
}
