<?php

namespace Drupal\static_export_data_resolver_graphql;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\graphql\GraphQL\Execution\QueryProcessor;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_suite\Entity\EntityUtils;
use Drupal\static_suite\StaticSuiteUserException;
use GraphQL\Server\OperationParams;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * A handler for querying GraphQL.
 */
class GraphqlQueryHandler implements GraphqlQueryHandlerInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * GraphQL query processor.
   *
   * @var \Drupal\graphql\GraphQL\Execution\QueryProcessor
   */
  protected $queryProcessor;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\graphql\GraphQL\Execution\QueryProcessor $queryProcessor
   *   GraphQL query processor.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Entity utils service.
   */
  public function __construct(AccountProxyInterface $currentUser, AccountSwitcherInterface $accountSwitcher, ConfigFactoryInterface $configFactory, LanguageManagerInterface $languageManager, QueryProcessor $queryProcessor, EntityUtils $entityUtils) {
    $this->currentUser = $currentUser;
    $this->accountSwitcher = $accountSwitcher;
    $this->configFactory = $configFactory;
    $this->languageManager = $languageManager;
    $this->queryProcessor = $queryProcessor;
    $this->entityUtils = $entityUtils;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \JsonException
   */
  public function query(string $graphqlQuery, array $variables = []): array {
    // Ensure "language" format (a common variable needed for querying multilingual
    // sites) is correct. Support simple and nested arrays.
    if (!empty($variables['language']['value'])) {
      $variables['language']['value'] = strtoupper(str_replace('-', '_', $variables['language']['value']));
    }
    elseif (!empty($variables['language'])) {
      $variables['language'] = strtoupper(str_replace('-', '_', $variables['language']));
    }

    // Flatten nested arrays. Support simple and nested arrays.
    $toValue = static function ($variableData) {
      return $variableData['value'] ?? $variableData;
    };

    $bodyParams = [
      'query' => $graphqlQuery,
      'variables' => json_encode(array_map($toValue, $variables), JSON_THROW_ON_ERROR),
      'operationName' => NULL,
    ];

    $params = OperationParams::create($bodyParams, TRUE);
    // When running on CLI or under a cron process, there is no user context
    // and GraphQL queries are executed as an anonymous user, leading to several
    // problems and inconsistencies: data that is properly resolved when logged
    // on Drupal's admin becomes non queryable when executing the same query on
    // CLI or cron
    // To avoid these problems, we ensure that all GraphQL queries are executed
    // by an authenticated user. Even though this can be considered a security
    // breach, in fact it simply executes a query to export the same data that
    // is being exported by logged in users. Once query is executed, the account
    // is switched back to its original anonymous user.
    $mustSwitchBack = FALSE;
    if ($this->currentUser->isAnonymous()) {
      $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
      $mustSwitchBack = TRUE;
    }
    $result = $this->queryProcessor->processQuery('default:default', $params);
    if ($mustSwitchBack) {
      $this->accountSwitcher->switchBack();
    }

    if (!empty($result->errors) && is_array($result->errors)) {
      throw new StaticSuiteUserException('Error on query: ' . $result->errors[0]);
    }

    return $result->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryFileContents(EntityInterface $entity, string $variant = NULL): ?string {
    $query = NULL;
    /** @var \Drupal\static_export\Entity\ExportableEntity $exportableEntity */
    $exportableEntity = $this->entityUtils->loadEntity('exportable_entity', $entity->getEntityTypeId() . '.' . $entity->bundle());
    if ($exportableEntity && $exportableEntity->status()) {
      // @todo - add event to alter the gql file path.
      $graphqlFile = $this->configFactory->get('static_export_data_resolver_graphql.settings')
        ->get('dir') . '/' . $exportableEntity->getEntityTypeIdString() . '/' . $exportableEntity->id() . ($variant ? ExporterPluginInterface::VARIANT_SEPARATOR . $variant : '') . '.gql';
      if (is_file($graphqlFile)) {
        // @todo - add event to alter the contents of $graphqlFile.
        $query = file_get_contents($graphqlFile);
      }
      else {
        throw new StaticSuiteUserException("Resolver query file located at '" . $graphqlFile . "' not found.");
      }
    }
    else {
      throw new StaticSuiteUserException("ExportableEntity for entity " . $entity->id() . " not found.");
    }

    // Show an error if query is using nodeById without a "content" alias.
    $converter = new CamelCaseToSnakeCaseNameConverter();
    $byIdQueryName = $converter->denormalize($entity->bundle()) . 'ById';

    if (strpos($query, $byIdQueryName . '(') !== FALSE && !preg_match("/content\s*:\s*$byIdQueryName\(/", $query)) {
      @trigger_error("Resolver query file located at '" . $graphqlFile . "' is using @byIdQueryName() query without a 'content' alias. It should be aliased this way: 'content:@byIdQueryName(...) { ... }'", ['@byIdQueryName' => $byIdQueryName]);
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryVariables(EntityInterface $entity, string $langcode = NULL): array {
    // @todo - add event to alter $variables before returning.
    // Any GraphQL query should be resolvable using these variables.
    // This group of variables should not contain the current language, since
    // that makes queries non-deterministic, returning different results
    // depending on the language of the UI being used to trigger an export
    // process.
    return [
      'entityId' => [
        'value' => $entity->id(),
        'type' => 'String',
      ],
      'uuid' => [
        'value' => $entity->uuid(),
        'type' => 'String',
      ],
      'entityLanguageId' => [
        // GraphQL supports only configurable languages (en, fr, es, etc) and
        // not locked languages (und and zxx). Instead of removing those locked
        // languages, we maintain them so a user-friendly error message is
        // thrown.
        'value' => strtoupper(str_replace('-', '_', $langcode)),
        'type' => 'LanguageId',
      ],
      'defaultLanguageId' => [
        'value' => strtoupper(str_replace('-', '_', $this->languageManager->getDefaultLanguage()
          ->getId())),
        'type' => 'LanguageId',
      ],
    ];
  }

}
