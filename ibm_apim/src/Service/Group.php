<?php
/********************************************************* {COPYRIGHT-TOP} ***
 * Licensed Materials - Property of IBM
 * 5725-L30, 5725-Z22
 *
 * (C) Copyright IBM Corporation 2018, 2024
 *
 * All Rights Reserved.
 * US Government Users Restricted Rights - Use, duplication or disclosure
 * restricted by GSA ADP Schedule Contract with IBM Corp.
 ********************************************************** {COPYRIGHT-END} **/

namespace Drupal\ibm_apim\Service;

use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Functionality for handling groups
 */
class Group {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * Group constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(StateInterface $state, LoggerInterface $logger) {
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * get all the groups
   *
   * @return array an array of the groups.
   */
  public function getAll(): array {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, NULL);

    $groups = $this->state->get('ibm_apim.groups');
    if ($groups === NULL || empty($groups)) {
      $groups = [];
    }

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, $groups);
    return $groups;
  }

  /**
   * get a specific group by url
   *
   * @param string $key
   *
   * @return null|array
   */
  public function get(string $key): ?array {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, $key);

    $group = NULL;
    if (isset($key)) {
      // clear caches if config different to previous requests
      $current_data = $this->state->get('ibm_apim.groups');

      if (isset($current_data[$key])) {
        $group = $current_data[$key];
      }
    }

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, $group);
    return $group;
  }

  /**
   * Update all groups
   *
   * @param array $data array of groups keyed on url
   */
  public function updateAll(array $data): void {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, $data);

    if (isset($data)) {
      $groups = [];
      foreach ($data as $group) {
        $groups[$group['url']] = $group;
      }
      $this->state->set('ibm_apim.groups', $groups);
    }

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }

  /**
   * Update a specific group
   *
   * @param string $key
   * @param array $data
   */
  public function update(string $key, array $data): void {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, $key);
    $corgService = \Drupal::service('ibm_apim.consumerorg');

    if (isset($key, $data)) {
      $current_data = $this->state->get('ibm_apim.groups');
      if (!is_array($current_data)) {
        $current_data = [];
      }

      //Remove old consumer orgs from this group
      if (isset($current_data[$key]['org_urls'])) {
        foreach ($current_data[$key]['org_urls'] as $org_url) {
          $org = $corgService->get($org_url);
          if (isset($org) && !in_array($org_url, $data['org_urls']) && $org->removeTag($data['url'])) {
            $corgService->createOrUpdateNode($org, 'internal-group');
          }
        }
      }

      $current_data[$key] = $data;

      // Update each consumer org in the group
      foreach ($data['org_urls'] as $org_url) {
        $org = $corgService->get($org_url);
        if (isset($org) && $org->addTag($data['url'])) {
          $corgService->createOrUpdateNode($org, 'internal-group');
        }
      }

      $this->state->set('ibm_apim.groups', $current_data);
    }

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }

  /**
   * Delete a specific group
   *
   * @param string $key (group url)
   */
  public function delete(string $key): void {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, $key);

    if (isset($key)) {
      $current_data = $this->state->get('ibm_apim.groups');

      if (isset($current_data)) {
        $new_data = [];
        foreach ($current_data as $url => $value) {
          if ($url !== $key) {
            $new_data[$url] = $value;
          }
        }
        $this->state->set('ibm_apim.groups', $new_data);
      }
    }

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }

  /**
   * Delete all current groups
   */
  public function deleteAll(): void {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, NULL);

    $this->state->set('ibm_apim.groups', []);

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }

}
