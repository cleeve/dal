<?php
namespace Packaged\Dal\Ql;

use Packaged\Config\ConfigurableInterface;
use Packaged\Dal\Exceptions\Connection\ConnectionException;
use Packaged\Config\ConfigurableTrait;
use Packaged\Helpers\ValueAs;

class PdoConnection
  implements IQLDataConnection, ConfigurableInterface, ILastInsertId
{
  use ConfigurableTrait;

  /**
   * @var \PDO
   */
  protected $_connection;

  /**
   * Open the connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function connect()
  {
    $dsn = $this->_config()->getItem('dsn', null);

    if($dsn === null)
    {
      $dsn = sprintf(
        "mysql:host=%s;dbname=%s;port=%d",
        $this->_config()->getItem('hostname', '127.0.0.1'),
        $this->_config()->getItem('database', 'packaged_dal'),
        $this->_config()->getItem('port', 3306)
      );
    }

    try
    {
      $this->_connection = new \PDO(
        $dsn,
        $this->_config()->getItem('username', 'root'),
        $this->_config()->getItem('password', ''),
        array_merge(
          $this->_defaultOptions(),
          ValueAs::arr($this->_config()->getItem('options'))
        )
      );
    }
    catch(\Exception $e)
    {
      throw new ConnectionException(
        "Failed to connect to PDO: " . $e->getMessage(),
        $e->getCode(), $e
      );
    }

    return $this;
  }

  /**
   * Default options for the PDO Connection
   * @return array
   */
  protected function _defaultOptions()
  {
    return [
      \PDO::ATTR_PERSISTENT => true,
      \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_TIMEOUT    => 5
    ];
  }

  /**
   * Check to see if the connection is already open
   *
   * @return bool
   */
  public function isConnected()
  {
    if($this->_connection === null)
    {
      return false;
    }
    else
    {
      try
      {
        $this->_connection->query("SELECT 1");
        return true;
      }
      catch(\Exception $e)
      {
        $this->_connection = null;
        return false;
      }
    }
  }

  /**
   * Disconnect the open connection
   *
   * @return static
   *
   * @throws ConnectionException
   */
  public function disconnect()
  {
    $this->_connection = null;
    return $this;
  }

  /**
   * Execute a query
   *
   * @param       $query
   * @param array $values
   *
   * @return int number of affected rows
   *
   * @throws ConnectionException
   */
  public function runQuery($query, array $values = null)
  {
    $statement = $this->_connection->prepare($query);
    $statement->execute($values);
    if($statement->errorCode() > 0)
    {
      $err = $statement->errorInfo();
      throw new ConnectionException($err[2], $err[1]);
    }
    return $statement->rowCount();
  }

  /**
   * Fetch the results of the query
   *
   * @param       $query
   * @param array $values
   *
   * @return array
   */
  public function fetchQueryResults($query, array $values = null)
  {
    $statement = $this->_connection->prepare($query);
    $statement->execute($values);
    $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
    return (array)$results;
  }

  /**
   * Retrieve the last inserted ID
   *
   * @return mixed
   */
  public function getLastInsertId()
  {
    return $this->_connection->lastInsertId();
  }
}
