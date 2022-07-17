<?php

namespace Drupal\react_webform_backend\Plugin\rest\resource;

use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a resource for submitting webforms.
 *
 * @RestResource(
 *   id = "react_webform_backend",
 *   label = @Translation("Webform Submit"),
 *   uri_paths = {
 *     "canonical" = "/react_webform_backend/submit",
 *     "create" = "/react_webform_backend/submit"
 *   }
 * )
 */
class WebformSubmitResource extends ResourceBase {

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->request = $container->get('request_stack');
    return $instance;
  }

  /**
   * Responds to entity POST requests and saves the new entity.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws HttpException in case of error.
   */
  public function post() {
    $webform_data = $this->request->getCurrentRequest()->getContent();
    if (empty($webform_data)) {
      return new JsonResponse([
        'error' => [
          'code' => '400',
          'message' => 'No data has been submitted.',
        ],
      ], 400);
    }
    $webform_data = json_decode($webform_data, TRUE);
    // Basic check for webform ID.
    if (empty($webform_data['webform_id'])) {
      return new JsonResponse([
        'error' => [
          'code' => '400',
          'message' => 'Missing webform id',
        ],
      ], 400);
    }

    $entity_type = NULL;
    $entity_id = NULL;

    if (!empty($webform_data['entityType']) && !empty($webform_data['entityId'])) {
      $entity_type = $webform_data['entityType'];
      $entity_id = $webform_data['entityId'];
    }

    // Check for a valid webform.
    $webform = Webform::load($webform_data['webform_id']);
    if (!$webform) {
      return new ModifiedResourceResponse([
        'error' => [
          'message' => 'Invalid webform_id value.',
        ],
      ], 400);
    }

    // Convert to webform values format.
    $current_request = \Drupal::requestStack()->getCurrentRequest();
    $values = [
      'in_draft' => FALSE,
      'uid' => \Drupal::currentUser()->id(),
      'uri' => '/react_webform_backend/submit' . $webform_data['webform_id'],
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      // Check if remote IP address should be stored.
      'remote_addr' => $webform->hasRemoteAddr() ? $current_request->getClientIp() : '',
      'webform_id' => $webform_data['webform_id'],
    ];

    $values['data'] = $webform_data;

    // Don't submit webform ID.
    unset($values['data']['webform_id']);

    // Don't submit entity data.
    unset($values['data']['entityType']);
    unset($values['data']['entityId']);

    // Check if webform is open.
    $is_open = WebformSubmissionForm::isOpen($webform);

    if ($is_open === TRUE) {
      $webform_submission = WebformSubmissionForm::submitFormValues($values);

      // Check if submit was successful.
      if ($webform_submission instanceof WebformSubmissionInterface) {
        return new ModifiedResourceResponse([
          'sid' => $webform_submission->id(),
          'settings' => self::getWhitelistedSettings($webform),
        ]);
      }
      else {
        // Return validation errors.
        return new ModifiedResourceResponse([
          'error' => $webform_submission,
        ], 400);
      }
    }
    else {
      return new ModifiedResourceResponse([
        'error' => [
          'message' => 'This webform is closed, or too many submissions have been made.',
        ],
      ], 400);
    }
  }

  static private function getWhitelistedSettings(WebformInterface $webform) {
    $whitelist = [
      'confirmation_url',
      'confirmation_type',
      'confirmation_message',
      'confirmation_title',
    ];

    return array_intersect_key(
      $webform->getSettings(),
      array_flip($whitelist)
    );
  }

}
