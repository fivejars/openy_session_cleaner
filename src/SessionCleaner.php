<?php

namespace Drupal\openy_session_cleaner;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class SessionCleaner.
 *
 * @package Drupal\openy_session_cleaner
 */
class SessionCleaner {

  use StringTranslationTrait;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * SessionCleaner constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('openy_session_cleaner');
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Cleans outdated sessions and classes.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function clean() {
    $config = $this->configFactory->get('openy_session_cleaner.settings');
    $limit = $config->get('limit');

    $sessions = $this->getOutdatedSessions($limit);
    if ($sessions) {
      $this->delete($sessions);
    }

    $classes = $this->getOutdatedClasses($limit);
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
    $query->addField('nfst', 'entity_id');
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
    $query->condition('n.type', 'class');
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
    /** @var \Drupal\node\Entity\Node[] $entities */
    $entities = $storage->loadMultiple($nids);
    if ($entities) {
      $storage->delete($entities);
      $this->saveLog($entities);
    }
  }

  /**
   * Saves log record about removed nodes.
   *
   * @param array $entities
   *   Array of removed entities.
   */
  protected function saveLog(array $entities) {
    /** @var \Drupal\node\Entity\Node[] $entities */
    $log = $this->formatPlural(count($entities),'1 node has been removed:', '@count nodes have been removed:')->__toString();

    $logs = [];
    $format = "%s '%s' (%d)";
    foreach ($entities as $entity) {
      $bundle = Unicode::ucfirst($entity->bundle());
      $logs[] = sprintf($format, $bundle, $entity->getTitle(), $entity->id());
    }

    $log .= PHP_EOL;
    $log .= implode(PHP_EOL, $logs);

    $this->logger->info($log);
  }

}
