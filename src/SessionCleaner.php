<?php

namespace Drupal\openy_session_cleaner;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Class SessionCleaner.
 *
 * @package Drupal\openy_session_cleaner
 */
class SessionCleaner {

  /**
   * Watchdog logger channel for openy_session_cleaner.
   *
   * @var LoggerChannelInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SessionCleaner constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('openy_session_cleaner');
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Cleans outdated sessions and classes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function clean() {
    $sessions = $this->getOutdatedSessions();
    if ($sessions) {
      $this->delete($sessions);
    }

    $classes = $this->getOutdatedClasses();
    if ($classes) {
      $this->delete($classes);
    }
  }

  /**
   * Gets outdated sessions NIDs.
   *
   * @param int $limit
   *   Query limit.
   *
   * @return mixed
   *   Array of the sessions NIDs or FALSE.
   */
  public function getOutdatedSessions($limit = 20) {
    $query = $this->connection->select('paragraph__field_session_time_date', 'pstd');
    $query->where('pstd.field_session_time_date_end_value < CURRENT_DATE()');
    $query->innerJoin('node__field_session_time', 'nfst', 'pstd.entity_id = nfst.field_session_time_target_id');
    $query->addField('nfst', 'field_session_time_target_id');
    $query->range(0, $limit);

    return $query->execute()->fetchCol();
  }

  /**
   * Gets classes without sessions.
   *
   * @param int $limit
   *   Query limit.
   *
   * @return mixed
   *   Array of the classes NIDs or FALSE.
   */
  public function getOutdatedClasses($limit = 20) {
    $query = $this->connection->select('node', 'n');
    $query->condition('n.bundle', 'class');
    $query->leftJoin('node__field_session_class', 'nfsc', 'nfsc.field_session_class_target_id = n.nid');
    $query->isNull('nfsc.entity_id');
    $query->addField('n', 'nid');
    $query->range(0, $limit);

    return $query->execute()->fetchCol();
  }

  /**
   * Deletes nodes by NIDs.
   *
   * @param array $nids
   *   Array of NIDs to remove.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(array $nids) {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->delete($storage->loadMultiple($nids));
  }

}
