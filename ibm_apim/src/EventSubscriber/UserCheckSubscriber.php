<?php

/********************************************************* {COPYRIGHT-TOP} ***
 * Licensed Materials - Property of IBM
 * 5725-L30, 5725-Z22
 *
 * (C) Copyright IBM Corporation 2024
 *
 * All Rights Reserved.
 * US Government Users Restricted Rights - Use, duplication or disclosure
 * restricted by GSA ADP Schedule Contract with IBM Corp.
 ********************************************************** {COPYRIGHT-END} **/

namespace Drupal\ibm_apim\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Messenger\Messenger;

class UserCheckSubscriber implements EventSubscriberInterface
{

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;
  /**
   * APIMServer constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   */
  public function __construct(
    LoggerInterface $logger,
    Messenger $messenger
  ) {
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function userCheck(ResponseEvent $event): void
  {
    // Stop unauthorized users from accessing /user routes to prevent gaining knowledge about which users exist
    $routeObject = \Drupal::routeMatch()->getRouteObject();
    $uri = \Drupal::request()->getRequestUri();
    $basePathEscaped = str_replace('/', '\/', base_path());
    $basePathTrimmed = substr($basePathEscaped, 2);
    if ($routeObject !== NULL && preg_match('/^\/('. $basePathTrimmed. ')?user\/([0-9]+)/', $uri, $matches)) {
      $pathUrl = $routeObject->getPath();
      $userId = $matches[2];

      // a user is not authorized to access another user's page unless they are an admin
      $userUnauthorized = (int) \Drupal::currentUser()->id() !== (int) $userId && !((int) \Drupal::currentUser()->id() === 1 ||  \Drupal::currentUser()->hasPermission('access user profiles'));

      if (\Drupal::currentUser()->isAuthenticated()) {
        if ($userUnauthorized) {
          $response = new RedirectResponse(Url::fromRoute('system.403')->toString(), 307);
          $event->setResponse($response);
        }

      } else if ($userUnauthorized && $pathUrl === '/search404') {

        // redirect 307 login page when the queried user doesn't exist, intentionally matching the 307 HTTP response for existing users
        $loginUrl = Url::fromRoute('user.login', ['destination' => base_path() . 'user/' . $userId], ['absolute' => TRUE])->toString();
        $response = new RedirectResponse($loginUrl, 307);
        $event->setResponse($response);
        $this->messenger->addError(t('Access denied. You must log in to view this page.'));

      }
    }
  }

  /**
   * @return array
   */
  public static function getSubscribedEvents()
  {
    $events[KernelEvents::RESPONSE][] = ['userCheck'];
    return $events;
  }
}
