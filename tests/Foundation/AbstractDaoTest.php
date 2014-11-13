<?php
namespace Foundation;

use Packaged\Config\ConfigProviderInterface;
use Packaged\Dal\DalResolver;
use Packaged\Dal\Foundation\AbstractDao;
use Packaged\Dal\Foundation\Dao;

class AbstractDaoTest extends \PHPUnit_Framework_TestCase
{
  public function testGetProperties()
  {
    $dao = new MockAbstractDao();
    $this->assertEquals(['name', 'email'], $dao->getDaoProperties());
  }

  public function testGetId()
  {
    $dao        = new MockAbstractDao();
    $dao->email = 'test@example.com';
    $this->assertEquals($dao->email, $dao->getId());
    $this->assertEquals(['email'], $dao->getDaoIDProperties());

    $dao         = new MockMultiIdAbstractDao();
    $dao->status = 'disabled';
    $dao->email  = 'test@example.com';
    $this->assertEquals(
      ['status' => $dao->status, 'email' => $dao->email],
      $dao->getId()
    );
    $this->assertEquals(['status', 'email'], $dao->getDaoIDProperties());

    $dao = new MockAbstractBaseDao();
    $this->assertEquals(['id'], $dao->getDaoIDProperties());
  }

  public function testGetPropertyData()
  {
    $dao = new MockAbstractDao();

    $dao->name = 'John Smith';
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );

    $dao->email = 'john@example.com';
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'john@example.com'],
      $dao->getDaoPropertyData()
    );
  }

  public function testGetChanges()
  {
    $dao = new MockAbstractDao();
    $this->assertEmpty($dao->getDaoChanges());
    $dao->markDaoDatasetAsSaved();
    $dao->email = 'john@example.com';
    $this->assertEquals(
      ['email' => ['from' => 'nobody@example.com', 'to' => $dao->email]],
      $dao->getDaoChanges()
    );
  }

  public function testPropertyGetSet()
  {
    $dao = new MockAbstractDao();
    $this->assertEquals('nobody@example.com', $dao->getDaoProperty('email'));
    $dao->setDaoProperty('email', 'john@example.com');
    $this->assertEquals('john@example.com', $dao->getDaoProperty('email'));
  }

  public function testHydrate()
  {
    $dao = new MockAbstractDao();
    $dao->hydrateDao(['name' => 'John Smith']);
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );
  }

  public function testConfigGet()
  {
    $dao = new MockAbstractDao();
    $this->assertInstanceOf(
      ConfigProviderInterface::class,
      $dao->getConfiguration()
    );
  }

  public function testOverHydrate()
  {
    $dao = new MockAbstractDao();
    $dao->hydrateDao(['name' => 'John Smith', 'nondao' => 'miss']);
    $this->assertEquals(
      ['name' => 'John Smith', 'email' => 'nobody@example.com'],
      $dao->getDaoPropertyData()
    );
    $this->assertFalse(isset($dao->nodao));
  }

  public function testDalResolver()
  {
    $resolver = new DalResolver();
    $resolver->boot();
    $resolver->addDataStore('test', $this->getMock('\Packaged\Dal\IDataStore'));
    $mock = new MockAbstractDao();
    $mock->init();
    $this->assertInstanceOf('\Packaged\Dal\IDataStore', $mock->getDataStore());
    $this->assertSame($resolver, $mock->getDalResolver());
    Dao::unsetDalResolver();
    $this->assertNull($mock->getDalResolver());
  }

  public function testJsonSerialize()
  {
    $dao       = new MockAbstractDao();
    $dao->name = "Brooke";
    $this->assertEquals(
      '{"name":"Brooke","email":"nobody@example.com"}',
      json_encode($dao)
    );
  }
}

class MockAbstractBaseDao extends AbstractDao
{
}

class MockAbstractDao extends AbstractDao
{
  public $name;
  public $email = 'nobody@example.com';

  public function init()
  {
    $this->_setDataStoreName('test');
  }

  public function getDaoIDProperties()
  {
    return ['email'];
  }
}


class MockMultiIdAbstractDao extends AbstractDao
{
  public $name;
  public $status = 'active';
  public $email = 'nobody@example.com';

  public function init()
  {
    $this->_setDataStoreName('test');
  }

  public function getDaoIDProperties()
  {
    return ['status', 'email'];
  }
}
