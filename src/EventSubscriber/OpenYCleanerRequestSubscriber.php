<?php

declare(strict_types=1);

namespace Drupal\openy_session_cleaner\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\node\NodeInterface;
use Drupal\openy_session_cleaner\SessionCleaner;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to Request events.
 */
class OpenYCleanerRequestSubscriber implements EventSubscriberInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The Open Y sessions cleaner service.
   *
   * @var \Drupal\openy_session_cleaner\SessionCleaner
   */
  protected SessionCleaner $sessionCleaner;

  /**
   * OpenYCleanerRequestSubscriber constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\openy_session_cleaner\SessionCleaner $session_cleaner
   *   The Open Y sessions cleaner service.
   */
  public function __construct(RouteMatchInterface $route_match, SessionCleaner $session_cleaner) {
    $this->routeMatch = $route_match;
    $this->sessionCleaner = $session_cleaner;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }

  /**
   * A method to be called whenever a kernel.request event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event triggered by the request.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    if ($route && !$route->getOption('_admin_route')) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $this->routeMatch->getParameter('node');

      if ($node instanceof NodeInterface && $node->getType() == 'class') {
        $active_session = $this->sessionCleaner->getClassActiveSessions((int) $node->id());
        if (empty($active_session)) {
          throw new NotFoundHttpException();
        }
      }
    }
  }

}
