<?php

namespace Drupal\static_export_data_resolver_graphql\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configuration form for GraphQL Resolver.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_data_resolver_graphql_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_data_resolver_graphql.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_data_resolver_graphql.settings');

    $form['dir'] = [
      '#type' => 'textfield',
      '#prefix' => $this->t('GraphQL data resolver obtains its data by executing a GraphQL query. For each <a href="@exportable_entity_url">exportable entity</a> using this resolver, there is a <strong>.gql</strong> file containing that query. Define where are those .gql files stored.', [
        '@exportable_entity_url' => Url::fromRoute('entity.exportable_entity.collection')
          ->toString(),
      ]),
      '#title' => $this->t('Directory for .gql files'),
      '#required' => TRUE,
      '#description' => $this->t('Path to the directory where GraphQL query files are located, e.g.- %example_path (or %example_path_specific if you want to be more specific and plan to use <a href="@graphql_fragment_include" target="_blank">GraphQL fragments</a> or other custom GraphQL queries). It must start with a leading slash. Relative to <em>DRUPAL_ROOT</em> (%drupal_root).', [
        '%example_path' => '/sites/default/graphql',
        '%example_path_specific' => '/sites/default/graphql/queries/entities',
        '@graphql_fragment_include' => 'https://www.drupal.org/project/graphql_fragment_include',
        '%drupal_root' => DRUPAL_ROOT,
      ]),
      '#default_value' => $config->get('dir'),
    ];

    $form['variables'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('GraphQL variables'),
      '#description' => $this->t('This resolver will provide four GraphQL variables to be used inside the .gql file: <ul><li>$entityId</li><li>$uuid</li><li>$entityLanguageId</li><li>$defaultLanguageId</li></ul>'),
    ];

    $form['variables']['gql_example'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('GraphQL file example'),
      '#prefix' => $this->t('You can use them this way:'),
    ];

    $form['variables']['gql_example']['code'] = [
      '#markup' => '<pre>
query Node($entityId: String!, $entityLanguageId: LanguageId!) {
    content:nodeById(id: $entityId, language: $entityLanguageId) {
        id: entityId
        type: entityType
        bundle: entityBundle
        isPublished: status
        ...
    }
}</pre>',
    ];

    $form['variables']['gql_example']['content'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('* IMPORTANT NOTE'),
      '#description' => $this->t('You should always use an alias of name "content" for your "*ById()" queries (e.g.- content:nodeById(...). This enables your site to use advanced preview modules like "Static Preview Gatsby Instant" (included with Static Suite).'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Advanced'),
    ];

    $form['advanced']['gql_variants'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Data variants'),
      '#description' => $this->t('This resolver supports "variants", a convenient way of getting a stripped down version of an entity. For example, when an entity\'s exported file contains hundreds of lines and is being included in another file (using the above "data includes" functionality), it could slowdown the build process. Using a variant with only the required data for that page could dramatically improve build times. To use variants, if you have a .gql file called <em>node.article.gql</em>, create a new .gql file named <em>node.article.[variant].gql</em> (e.g.- <em>node.article.card.gql</em>)'),
    ];

    $form['advanced']['data_includes'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Data includes'),
    ];

    $form['advanced']['data_includes']['code'] = [
      '#prefix' => '<p>' . $this->t('This resolver supports "data includes", a way of including data from a export file inside another. This is a key functionality when you need that a exported file contains <strong>fresh data</strong> from its referenced entities.') . '</p><p>' . $this->t('This module offers four GraphQL fields:') . '</p><ol><li><em>entityInclude(variant: String)</em>: mainly used inside entity reference fields</li><li><em>configInclude(name: String, language: LanguageId, variant: String)</em>: not normally used because configuration is usually defined globally in SSGs</li><li><em>localeInclude(language: LanguageId, variant: String)</em>: not normally used because locales are usually defined globally in SSGs</li><li><em>customInclude(basedir: String, dir: String!, filename: String!, extension: String, language: LanguageIdAll)</em>: useful only for custom exporters</li></ol>',
      '#markup' => '<pre>DATA INCLUDES EXAMPLE
{
    ...
    fieldRelatedProduct {
        entity {
            entityInclude(variant: "card")
        }
    }
    ...
}</pre>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $graphqlDir = $form_state->getValue('dir');
    if (strpos($graphqlDir, '/') !== 0) {
      $form_state->setErrorByName(
        'dir',
        $this->t('Directory for .gql files must start with a leading slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_data_resolver_graphql.settings');
    $config
      ->set('dir', rtrim($form_state->getValue('dir'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
