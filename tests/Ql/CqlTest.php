<?php
namespace Ql;

use cassandra\CqlPreparedResult;
use Packaged\Config\Provider\ConfigSection;
use Packaged\Dal\DalResolver;
use Packaged\Dal\DataTypes\Counter;
use Packaged\Dal\Foundation\Dao;
use Packaged\Dal\Ql\Cql\CqlConnection;
use Packaged\Dal\Ql\Cql\CqlDao;
use Packaged\Dal\Ql\Cql\CqlDaoCollection;
use Packaged\Dal\Ql\Cql\CqlDataStore;
use Packaged\Dal\Ql\IQLDataConnection;
use Packaged\QueryBuilder\Builder\QueryBuilder;
use Packaged\QueryBuilder\Expression\ValueExpression;
use Packaged\QueryBuilder\Predicate\EqualPredicate;

require_once 'supporting.php';

class CqlTest extends \PHPUnit_Framework_TestCase
{
  /**
   * @var CqlConnection
   */
  private static $_connection;

  public static function setUpBeforeClass()
  {
    self::$_connection = new CqlConnection();
    self::$_connection->connect();
    self::$_connection->runQuery(
      "CREATE KEYSPACE IF NOT EXISTS packaged_dal WITH REPLICATION = "
      . "{'class' : 'SimpleStrategy','replication_factor' : 1};"
    );
    self::$_connection->runQuery(
      'DROP TABLE IF EXISTS packaged_dal.mock_ql_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal.mock_ql_daos ('
      . '"id" varchar,'
      . '"id2" int,'
      . '"username" varchar,'
      . '"display" varchar,'
      . '"intVal" int,'
      . '"bigintVal" bigint,'
      . '"doubleVal" double,'
      . '"floatVal" float,'
      . '"boolVal" boolean,'
      . ' PRIMARY KEY ((id), id2));'
    );
    self::$_connection->runQuery(
      'DROP TABLE IF EXISTS packaged_dal.mock_counter_daos'
    );
    self::$_connection->runQuery(
      'CREATE TABLE packaged_dal.mock_counter_daos ('
      . '"id" varchar PRIMARY KEY,'
      . '"c1" counter,'
      . '"c2" counter,'
      . ');'
    );
  }

  public function testNoKeyspace()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $connection->connect();
    $connection->setConfig('keyspace', 'packaged_dal');
    $datastore->setConnection($connection);

    $dao = new MockCqlDao();
    $dao->id = 'test2';
    $dao->id2 = 9876;
    $dao->username = 'daotest';
    $datastore->save($dao);
    $this->assertTrue($datastore->exists($dao));
  }

  protected function _configureConnection(CqlConnection $conn)
  {
    $conn->setReceiveTimeout(5000);
    $conn->setSendTimeout(5000);
    $conn->setConfig('connect_timeout', 1000);
    $conn->setConfig('keyspace', 'packaged_dal');
  }

  public function testConnection()
  {
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $this->assertFalse($connection->isConnected());
    $connection->connect();
    $this->assertTrue($connection->isConnected());
    $connection->disconnect();
    $this->assertFalse($connection->isConnected());
  }

  public function testConnectionException()
  {
    $connection = new CqlConnection();
    $config = new ConfigSection();
    $config->addItem('hosts', '255.255.255.255');
    $connection->configure($config);

    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\ConnectionException'
    );
    $connection->connect();
  }

  public function testLsd()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();

    $dao = new MockCqlDao();
    $dao->id = uniqid('daotest');
    $dao->id2 = 12345;
    $dao->username = time() . 'user';
    $dao->display = 'User ' . date("Y-m-d");
    $dao->intVal = 123456;
    $dao->bigintVal = -123456;
    $dao->doubleVal = 123456;
    $dao->floatVal = 12.3456;
    $dao->boolVal = true;
    $datastore->save($dao);
    $dao->username = 'test 1';
    $dao->display = 'Brooke';
    $datastore->save($dao);
    $dao->username = 'test 2';
    $datastore->load($dao);
    $this->assertEquals('test 1', $dao->username);
    $this->assertEquals(123456, $dao->intVal);
    $this->assertEquals(-123456, $dao->bigintVal);
    $this->assertEquals(123456, $dao->doubleVal);
    $this->assertEquals(12.3456, $dao->floatVal, '', 0.00001);
    $this->assertTrue($dao->boolVal);
    $dao->display = 'Save 2';
    $datastore->save($dao);
    $datastore->delete($dao);

    $this->assertEquals(
      ['id' => $dao->id, 'id2' => $dao->getPropertySerialized('id2', 12345)],
      $dao->getId()
    );
  }

  public function testConnectionConfig()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $connection->setReceiveTimeout(123);
    $this->assertEquals(123, $connection->getConfig('receive_timeout'));
    $connection->setSendTimeout(123);
    $this->assertEquals(123, $connection->getConfig('send_timeout'));
    $connection->setConfig('connect_timeout', 123);
    $this->assertEquals(123, $connection->getConfig('connect_timeout'));
    $connection->disconnect();
  }

  public function testPrepareException()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
    $connection->prepare("INVALID");
  }

  public function testExecuteException()
  {
    $connection = new MockCqlConnection();
    $this->_configureConnection($connection);
    $connection->connect();
    $this->setExpectedException(
      '\Packaged\Dal\Exceptions\Connection\CqlException'
    );
    $connection->execute(new CqlPreparedResult());
  }

  public function testGetData()
  {
    $datastore = new MockCqlDataStore();
    $connection = new MockCqlConnection();
    $datastore->setConnection($connection);

    $dao = new MockCqlDao();
    $dao->id = 'test1';
    $dao->id2 = 5555;
    $dao->username = 'testuser';
    $datastore->save($dao);

    $eq = new EqualPredicate();
    $eq->setField('id');
    $eq->setExpression(ValueExpression::create('test1'));
    $d = $datastore->getData(
      QueryBuilder::select()->from($dao->getTableName())->where($eq)
    );

    $testDao = new MockCqlDao();
    $testDao->hydrateDao($d[0], true);
    $testDao->markDaoAsLoaded();
    $testDao->markDaoDatasetAsSaved();

    $this->assertEquals($dao, $testDao);
  }

  public function testTtl()
  {
    $connection = new MockAbstractQlDataConnection();
    $datastore = new MockCqlDataStore();
    $datastore->setConnection($connection);
    $dao = new MockCqlDao();
    $dao->id = 3;
    $dao->id2 = 1234;
    $dao->username = 'testuser';
    $dao->setTtl(100);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "boolVal") '
      . 'VALUES (3, 1234, \'testuser\', NULL, NULL, NULL, NULL, NULL, NULL) USING TTL 100',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [],
      $connection->getExecutedQueryValues()
    );

    $dao = new MockCqlDao();
    $dao->id = 'test4';
    $dao->id2 = 4321;
    $dao->username = 'testuser';
    $dao->setTtl(null);
    $datastore->save($dao);
    $this->assertEquals(
      'INSERT INTO "mock_ql_daos" ("id", "id2", "username", "display", "intVal", "bigintVal", "doubleVal", "floatVal", "boolVal") '
      . 'VALUES (\'test4\', 4321, \'testuser\', NULL, NULL, NULL, NULL, NULL, NULL)',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [],
      $connection->getExecutedQueryValues()
    );

    $dao->setTtl(101);
    $dao->setTimestamp(123456);
    $dao->username = "test";
    $datastore->save($dao);
    $this->assertEquals(
      'UPDATE "mock_ql_daos" USING TTL 101 AND TIMESTAMP 123456 SET "username" = \'test\' WHERE "id" = \'test4\' AND "id2" = 4321',
      $connection->getExecutedQuery()
    );
    $this->assertEquals(
      [],
      $connection->getExecutedQueryValues()
    );

    $cqlDao = $this->getMockForAbstractClass(CqlDao::class);
    $this->assertInstanceOf(CqlDao::class, $cqlDao);
    /**
     * @var $cqlDao CqlDao
     */
    $this->assertNull($cqlDao->getTtl());
  }

  public function testCollection()
  {
    $this->assertInstanceOf(CqlDaoCollection::class, MockCqlDao::collection());

    $dataStore = new MockCqlDataStore();
    $dataStore->setConnection(new CqlConnection());
    $mockDao = new MockCqlDao();
    $mockDao->setDataStore($dataStore);
    $collection = MockCqlCollection::createFromDao($mockDao);
    $data = $collection->loadWhere()->getRawArray();
    $this->assertNotEmpty($data);
    $this->assertInstanceOf(MockCqlDao::class, $data[0]);
  }

  public function testCounters()
  {
    $datastore = new MockCqlDataStore();
    $connection = new CqlConnection();
    $this->_configureConnection($connection);
    $datastore->setConnection($connection);
    $connection->connect();
    $resolver = new DalResolver();
    $resolver->boot();
    Dao::getDalResolver()->addDataStore('mockcql', $datastore);

    $dao = new MockCounterCqlDao();
    $dao->id = 'test1';
    $dao->c1->increment(10);
    $dao->c1->decrement(5);
    $datastore->save($dao);
    $dao->c2->increment(1);
    $dao->c2->decrement(3);
    $datastore->save($dao);

    $loaded = MockCounterCqlDao::loadById('test1');
    $this->assertEquals(5, $loaded->c1->calculated());
    $this->assertEquals(-2, $loaded->c2->calculated());
  }
}

class MockCqlCollection extends CqlDaoCollection
{
  public static function createFromDao(CqlDao $dao)
  {
    $collection = parent::create(get_class($dao));
    $collection->_dao = $dao;
    return $collection;
  }
}

class MockCqlConnection extends CqlConnection
{
  public function getConfig($item)
  {
    return $this->_config()->getItem($item);
  }
}

class MockCqlDataStore extends CqlDataStore
{
  public function setConnection(IQLDataConnection $connection)
  {
    $this->_connection = $connection;
    return $this;
  }
}

class MockCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockcql';
  protected $_ttl;
  protected $_timestamp;

  public $id;
  /**
   * @int
   */
  public $id2;
  public $username;
  public $display;
  /**
   * @int
   */
  public $intVal;
  /**
   * @bigint
   */
  public $bigintVal;
  /**
   * @double
   */
  public $doubleVal;
  /**
   * @float
   */
  public $floatVal;
  /**
   * @bool
   */
  public $boolVal;

  protected $_dataStore;

  public function getDaoIDProperties()
  {
    return ['id', 'id2'];
  }

  public function getTableName()
  {
    return "mock_ql_daos";
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function getTimestamp()
  {
    return $this->_timestamp;
  }

  public function setTimestamp($timestamp)
  {
    $this->_timestamp = $timestamp;
    return $this;
  }

  public function setDataStore(CqlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }
}

class MockCounterCqlDao extends CqlDao
{
  protected $_dataStoreName = 'mockcql';
  protected $_ttl;

  public $id;
  /**
   * @counter
   * @var Counter
   */
  public $c1;
  /**
   * @counter
   * @var Counter
   */
  public $c2;

  protected $_dataStore;

  protected $_tableName = 'mock_counter_daos';

  public function getTableName()
  {
    return $this->_tableName;
  }

  public function setTableName($table)
  {
    $this->_tableName = $table;
    return $this;
  }

  public function getTtl()
  {
    return $this->_ttl;
  }

  public function setTtl($ttl)
  {
    $this->_ttl = $ttl;
    return $this;
  }

  public function setDataStore(CqlDataStore $store)
  {
    $this->_dataStore = $store;
    return $this;
  }

  public function getDataStore()
  {
    if($this->_dataStore === null)
    {
      return parent::getDataStore();
    }
    return $this->_dataStore;
  }
}
