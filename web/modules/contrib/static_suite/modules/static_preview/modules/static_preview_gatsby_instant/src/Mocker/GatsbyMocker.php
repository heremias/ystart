<?php

namespace Drupal\static_preview_gatsby_instant\Mocker;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\node\NodeInterface;
use Drupal\static_build\Plugin\StaticBuilderHelperInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface;
use Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface;
use Drupal\static_preview_gatsby_instant\GraphQL\Data\Resolver\GraphqlNodePreviewDataResolverInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;
use Drupal\static_suite\StaticSuiteException;

/**
 * A mocker service to provide Gatsby with mocked data, components, etc.
 */
class GatsbyMocker implements GatsbyMockerInterface {

  /**
   * Drupal config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The URI resolver for page paths.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface
   */
  protected $pagePathUriResolver;

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Release manager.
   *
   * @var \Drupal\static_suite\Release\ReleaseManagerInterface
   */
  protected $releaseManager;

  /**
   * The static builder helper.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderHelperInterface
   */
  protected $staticBuilderHelper;

  /**
   * The data include loader.
   *
   * @var \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface
   */
  protected $dataIncludeLoader;

  /**
   * The GraphQL preview data resolver.
   *
   * @var \Drupal\static_preview_gatsby_instant\GraphQL\Data\Resolver\GraphqlNodePreviewDataResolverInterface
   */
  protected $graphqlNodePreviewDataResolver;

  /**
   * GatsbyMocker service constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder manager.
   * @param \Drupal\static_build\Plugin\StaticBuilderHelperInterface $staticBuilderHelper
   *   The static builder helper.
   * @param \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface $pagePathUriResolver
   *   The URI resolver for page paths.
   * @param \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderInterface $dataIncludeLoader
   *   The data include loader.
   * @param \Drupal\static_preview_gatsby_instant\GraphQL\Data\Resolver\GraphqlNodePreviewDataResolverInterface $graphqlNodePreviewDataResolver
   *   The GraphQL preview data resolver.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    AccountInterface $currentUser,
    LanguageManagerInterface $languageManager,
    PrivateTempStoreFactory $tempStoreFactory,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    StaticBuilderHelperInterface $staticBuilderHelper,
    PagePathUriResolverInterface $pagePathUriResolver,
    DataIncludeLoaderInterface $dataIncludeLoader,
    GraphqlNodePreviewDataResolverInterface $graphqlNodePreviewDataResolver
  ) {
    $this->configFactory = $configFactory;
    $this->currentUser = $currentUser;
    $this->languageManager = $languageManager;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticBuilderHelper = $staticBuilderHelper;
    $this->pagePathUriResolver = $pagePathUriResolver;
    $this->dataIncludeLoader = $dataIncludeLoader;
    $this->graphqlNodePreviewDataResolver = $graphqlNodePreviewDataResolver;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \JsonException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function getMockedPageData(string $pagePath): ?array {
    // EntityExporterUriResolverInterface ($this->entityExporterUriResolver)
    // needs a clean path without langcode, but we still need that langcode when
    // setting $previewPageDataArray['path']
    // Drupal supports different language detection methods (URL prefix,
    // session, user's language preference, browser's language settings, etc)
    // but only the first one (URL prefix) makes sense for Gatsby, where
    // different language versions of a page must be previously generated and
    // therefore its content cannot be changed by a cookie or browser's language
    // settings.
    $languageInfo = $this->getLanguageInfoFromPath($pagePath);
    $pagePathWithOutLangcode = $pagePath;
    if ($languageInfo['langcode'] && $languageInfo['prefix']) {
      $pagePathWithOutLangcode = (string) preg_replace("/^\/" . $languageInfo['prefix'] . "\//", '/', $pagePath);
    }

    $uri = NULL;
    $pageDataContentsArray = NULL;
    // @todo handle Drupal installed in a subdirectory.
    if (strpos($pagePath, '/node/preview/') === 0) {
      $pagePathParts = explode("/", $pagePath);
      $uuid = $pagePathParts[3];
      $store = $this->tempStoreFactory->get('node_preview');
      $tempNodeFormState = $store->get($uuid);
      if ($tempNodeFormState instanceof FormStateInterface) {
        $formObject = $tempNodeFormState->getFormObject();
        if ($formObject) {
          $node = $formObject->getEntity();
          if ($node instanceof NodeInterface) {
            $pageDataContentsArray = $this->graphqlNodePreviewDataResolver->resolve($node);
            $pageDataContentsString = json_encode($pageDataContentsArray, JSON_THROW_ON_ERROR);
            // We know this content mime type is JSON, since we just encoded it.
            $pageDataContents = $this->dataIncludeLoader->loadString($pageDataContentsString, 'application/json');
          }
        }
      }
    }
    else {
      $uri = $this->pagePathUriResolver->resolve($pagePathWithOutLangcode, $languageInfo['langcode']);
      $pageDataContents = $uri ? $this->dataIncludeLoader->loadUri($uri) : NULL;
    }
    if (!empty($pageDataContents)) {
      $pageDataContentsArray = json_decode($pageDataContents, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    if (!empty($pageDataContentsArray)) {
      $pageDataContentsArray['data']['content']['sourceFile'] = $uri ? $uri->getTarget() : NULL;
      $previewPageDataArray = $this->getPreviewComponentPageData();
      if (isset($previewPageDataArray['result']['pageContext'])) {
        // Hydrate page-data.json template with page's data.
        // Instead of using
        // $pageDataContentsArray['data']['content']['url']['path'], honor
        // $pagePath and set it to $previewPageDataArray['path']. This way,
        // accessing the same page from different aliases (like /node/XXX)
        // will always work.
        $previewPageDataArray['path'] = $pagePath[0] === "/" ? $pagePath : "/" . $pagePath;
        $previewPageDataArray['result']['pageContext']['node'] = $pageDataContentsArray;
        return $previewPageDataArray;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMockedPageHtml(string $pagePath): ?string {
    // Ensure $pathInfo always stars with a leading slash.
    if ($pagePath[0] !== '/') {
      $pagePath = '/' . $pagePath;
    }

    $previewHtml = NULL;
    try {
      $previewHtml = $this->getPreviewComponentHtml();
    }
    catch (PluginException | StaticSuiteException $e) {
      // Noop.
    }
    if ($previewHtml) {
      $previewData = 'window.pagePath="' . $pagePath . '";';
      // Check if we must show an info message about a running build taking
      // place.
      $runningBuildData = [];
      if ($this->currentUser->isAuthenticated()) {
        $runningBuildData = $this->staticBuilderHelper->getRunningBuildData('gatsby', StaticBuilderPluginInterface::RUN_MODE_PREVIEW);
      }
      if (count($runningBuildData) > 0) {
        $previewData .= 'window.GATSBY_INSTANT_PREVIEW___RUNNING_BUILD_DATA={';
        foreach ($runningBuildData as $key => $value) {
          if (is_scalar($value)) {
            $previewData .= str_replace('-', '_', $key) . ":";
            if ($value === NULL) {
              $previewData .= "null";
            }
            else {
              $previewData .= (is_numeric($value) ? $value : "'$value'");
            }
            $previewData .= ",";
          }
        }
        $previewData .= '};';
      }

      $previewHtml = str_replace(
      // Replace original window.pagePath.
        [
          'window.pagePath="' . $this->getPreviewComponentPathFromConfig() . '";',
          '<link as="fetch" rel="preload" href="/page-data' . $this->getPreviewComponentPathFromConfig() . '/page-data.json"',
        ],
        // Replace preload of preview component's original page-data.json.
        [
          $previewData,
          '<link as="fetch" rel="preload" href="/page-data' . ($pagePath === "/" ? "/index" : $pagePath) . '/page-data.json"',
        ], $previewHtml
      );

      return $previewHtml;
    }
    return NULL;
  }

  /**
   * Get preview component's page-data.json to use it as a template.
   *
   * @return array|null
   *   The page data in array format.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \JsonException
   */
  protected function getPreviewComponentPageData(): ?array {
    $currentRelease = $this->releaseManager()->getCurrentRelease();
    if ($currentRelease) {
      $previewPageDataPath = $currentRelease->getDir() . "/page-data" . $this->getPreviewComponentPathFromConfig() . "/page-data.json";
      $previewPageDataContents = @file_get_contents($previewPageDataPath);
      if (!empty($previewPageDataContents)) {
        $previewPageDataArray = json_decode($previewPageDataContents, TRUE, 512, JSON_THROW_ON_ERROR);
        if (isset($previewPageDataArray['result']['pageContext'])) {
          $previewPageDataArray['result']['pageContext']['isCreatedByStatefulCreatePages'] = FALSE;
          return $previewPageDataArray;
        }
      }
    }
    return NULL;
  }

  /**
   * Get preview component's html to use it as a template.
   *
   * @return string|null
   *   Component's html.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function getPreviewComponentHtml(): ?string {
    $currentRelease = $this->releaseManager()->getCurrentRelease();
    if ($currentRelease) {
      $previewHtmlPath = $currentRelease->getDir() . $this->getPreviewComponentPathFromConfig() . "/index.html";
      $previewHtmlContents = @file_get_contents($previewHtmlPath);
      if (!empty($previewHtmlContents)) {
        return $previewHtmlContents;
      }
    }
    return NULL;
  }

  /**
   * Get preview component path from config.
   *
   * @return string
   *   Preview component path from config.
   */
  protected function getPreviewComponentPathFromConfig(): string {
    $config = $this->configFactory->get('static_preview_gatsby_instant.settings');
    return $config->get('preview_component_path');
  }

  /**
   * Get release manager from plugin.
   *
   * @return \Drupal\static_suite\Release\ReleaseManagerInterface
   *   The plugin's release manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function releaseManager(): ReleaseManagerInterface {
    if (!$this->releaseManager) {
      // We know its id is "gatsby" from static_builder_gatsby, which is a
      // dependency of this module.
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => 'gatsby',
        'configuration' => [
          'run-mode' => $this->configFactory->get('static_preview_gatsby_instant.settings')
            ->get('run_mode'),
        ],
      ]);
      $this->releaseManager = $builder->getReleaseManager();
    }

    return $this->releaseManager;
  }

  /**
   * Given a path or alias, get its language info (langcode and prefix).
   *
   * This method is based on LanguageNegotiationUrl::getLangcode() which uses
   * the Request to obtain the langcode. Here we use the same logic but using a
   * plain string.
   *
   * We need langcode and prefix because prefix is editable at
   * /admin/config/regional/language/detection and it can be anything, even an
   * empty string
   *
   * @param string $path
   *   Path or alias.
   *
   * @return array
   *   An array with two elements, 'langcode' and 'prefix'
   * @see LanguageNegotiationUrl::getLangcode()
   */
  protected function getLanguageInfoFromPath(string $path): array {
    $languages = $this->languageManager->getLanguages();
    $config = $this->configFactory->get('language.negotiation')->get('url');

    $request_path = urldecode(trim($path, '/'));
    $path_args = explode('/', $request_path);
    $prefix = array_shift($path_args);

    // Search prefix within added languages.
    $negotiated_language = FALSE;
    foreach ($languages as $language) {
      if (isset($config['prefixes'][$language->getId()]) && $config['prefixes'][$language->getId()] === $prefix) {
        $negotiated_language = $language;
        break;
      }
    }

    $langcode = NULL;
    if ($negotiated_language) {
      $langcode = $negotiated_language->getId();
    }

    $langInfo = [
      'langcode' => NULL,
      'prefix' => NULL,
    ];

    if ($langcode) {
      $langInfo = [
        'langcode' => $langcode,
        'prefix' => $prefix,
      ];
    }

    return $langInfo;
  }

}
