<?php

declare(strict_types=1);

namespace Drupal\openy_session_cleaner;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Session Cleaner service.
 */
class SessionCleaner {

  use StringTranslationTrait;

  /**
   * Watchdog logger channel for openy_session_cleaner.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

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
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function clean(): void {
    $config = $this->configFactory->get('openy_session_cleaner.settings');
    $limit = $config->get('limit');
    $remove_without_schedule = $config->get('remove_sessions_without_time');
    $remove_empty_classes = $config->get('remove_empty_classes');

    if ($remove_without_schedule) {
      $sessions = $this->getSessionsWithoutSchedule($limit * 2);
      if ($sessions) {
        $this->delete($sessions);
      }
    }

    $sessions = $this->getOutdatedSessions($limit);
    if ($sessions) {
      $this->delete($sessions);
    }

    if ($remove_empty_classes) {
      $classes = $this->getOutdatedClasses($limit);
      if ($classes) {
        $this->delete($classes);
      }
    }
  }

  /**
   * Gets NIDs of the sessions that have no schedule.
   *
   * @param int $limit
   *   Query limit.
   *
   * @return array|int
   *   An integer for count queries or an array of ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSessionsWithoutSchedule(int $limit = 20): int|array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'session')
      ->notExists('field_session_time')
      ->accessCheck(FALSE)
      ->range(0, $limit);
    return $query->execute();
  }

  /**
   * Gets outdated sessions NIDs.
   *
   * @param int $limit
   *   Query limit.
   *
   * @return array
   *   An indexed array, or an empty array if there is no result set.
   */
  public function getOutdatedSessions(int $limit = 20): array {
    // Each session can contain several 'Session Time' entries.
    // So we should check max end date.
    $sub_query = $this->connection->select('paragraph__field_session_time_date', 'pfstd');
    $sub_query->addField('st', 'entity_id', 'p_nid');
    $sub_query->addExpression('MAX(pfstd.field_session_time_date_end_value)', 'max_date');
    $sub_query->innerJoin('node__field_session_time', 'st', 'pfstd.entity_id = st.field_session_time_target_id');
    $sub_query->groupBy('st.entity_id');

    $query = $this->connection->select('node__field_session_time', 'nfst');
    $query->addField('nfst', 'entity_id');
    $query->where('md.max_date < CURRENT_DATE()');
    $query->innerJoin($sub_query, 'md', 'p_nid = nfst.entity_id');
    $query->range(0, $limit);
    return $query->execute()->fetchCol();
  }

  /**
   * Gets classes without sessions.
   *
   * @param int $limit
   *   Query limit.
   *
   * @return array
   *   An indexed array, or an empty array if there is no result set.
   */
  public function getOutdatedClasses(int $limit = 20): array {
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
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete(array $nids): void {
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\Entity\Node[] $entities */
    $entities = $storage->loadMultiple($nids);
    if ($entities) {
      $storage->delete($entities);
      $this->saveLog($entities);
    }
  }

  /**
   * Gets active sessions for the given class.
   *
   * @param int $class_nid
   *   The class NID.
   *
   * @return array
   *   The array of active sessions for given class.
   */
  public function getClassActiveSessions(int $class_nid): array {
    $sub_query = $this->connection->select('paragraph__field_session_time_date', 'pfstd');
    $sub_query->addField('st', 'entity_id', 'p_nid');
    $sub_query->addExpression('MAX(pfstd.field_session_time_date_end_value)', 'max_date');
    $sub_query->innerJoin('node__field_session_time', 'st', 'pfstd.entity_id = st.field_session_time_target_id');
    $sub_query->groupBy('st.entity_id');

    $query = $this->connection->select('node__field_session_time', 'nfst');
    $query->addField('nfst', 'entity_id');
    $query->condition('nfsc.field_session_class_target_id', $class_nid);
    $query->condition('nfd.status', NodeInterface::PUBLISHED);
    $query->condition('nfd.type', 'session');
    $query->where('md.max_date >= CURRENT_DATE()');
    $query->innerJoin('node__field_session_class', 'nfsc', 'nfst.entity_id = nfsc.entity_id');
    $query->innerJoin($sub_query, 'md', 'p_nid = nfst.entity_id');
    $query->innerJoin('node_field_data', 'nfd', 'nfst.entity_id = nfd.nid');

    return $query->execute()->fetchCol();
  }

  /**
   * Saves log record about removed nodes.
   *
   * @param array $entities
   *   Array of removed entities.
   */
  protected function saveLog(array $entities): void {
    /** @var \Drupal\node\Entity\Node[] $entities */
    $log = $this->formatPlural(count($entities), '1 node has been removed:', '@count nodes have been removed:')->__toString();

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
