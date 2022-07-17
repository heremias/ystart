<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;

/**
 * A form base for other forms that depend on output formatters being.
 */
abstract class OutputFormatterDependentConfigFormBase extends ConfigFormBase {

  /**
   * The static output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected OutputFormatterPluginManagerInterface $outputFormatterManager;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $outputFormatters = $this->outputFormatterManager->getDefinitions();
    if (count($outputFormatters) === 0) {
      $this->messenger()
        ->addWarning(
          $this->t(
            'Access to this page is disabled until you activate one of the <strong>output formatter modules</strong> provided by Static Suite. Please visit the <a href="@url">module list</a>.',
            ['@url' => Url::fromRoute('system.modules_list')->toString()]
          )
        );
      return [];
    }

    return parent::buildForm($form, $form_state);
  }

}
