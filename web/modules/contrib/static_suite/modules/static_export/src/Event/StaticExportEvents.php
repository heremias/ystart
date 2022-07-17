<?php

namespace Drupal\static_export\Event;

/**
 * Contains all events dispatched by Static Export.
 *
 * There are two special events that appear repeated across several modules:
 *
 * 1) CHAINED_STEP_START
 * 2) CHAINED_STEP_END
 *
 * You can find them in static_export, static_build and static_deploy.
 *
 * Those three modules work in a chained mode, where each one is executed in an
 * order to provide the main functionality of Static Suite. Each one represents
 * a step in that chain of processes. Inside every step there is a set of
 * events, specific to that step, and they are usually grouped inside a
 * try/catch to be able to handle any situation that could arise when
 * exporting, building or deploying.
 *
 * On the contrary, CHAINED_STEP_START and CHAINED_STEP_END are triggered
 * outside that try/catch, and they express that one of those chaining steps is
 * finished so the next one could start. They are intentionally outside that
 * try/catch so they can handle they exceptions in isolation; otherwise, a
 * failed deploy triggered by a build would erroneously appear as a build
 * error.
 */
final class StaticExportEvents {

  public const CHAINED_STEP_START = 'static_export.chained_step_start';

  public const PREFLIGHT = 'static_export.preflight';

  public const START = 'static_export.start';

  public const CHECK_PARAMS_START = 'static_export.check_params.start';

  public const CHECK_PARAMS_END = 'static_export.check_params_end';

  public const CONFIG_START = 'static_export.config.start';

  public const CONFIG_END = 'static_export.config_end';

  public const RESOLVER_START = 'static_export.resolver.start';

  public const RESOLVER_END = 'static_export.resolver_end';

  public const FORMATTER_START = 'static_export.formatter.start';

  public const FORMATTER_END = 'static_export.formatter_end';

  public const OUTPUT_START = 'static_export.output.start';

  public const VARIANTS_EXPORT_START = 'static_export.variants_export.start';

  public const VARIANT_KEYS_DEFINED = 'static_export.variant_keys.defined';

  public const VARIANTS_EXPORT_END = 'static_export.variants_export.end';

  public const TRANSLATIONS_EXPORT_START = 'static_export.translations_export.start';

  public const TRANSLATION_KEYS_DEFINED = 'static_export.translation_keys.defined';

  public const TRANSLATIONS_EXPORT_END = 'static_export.translations_export.end';

  public const WRITE_START = 'static_export.write.start';

  public const WRITE_QUEUE_PROCESSING_START = 'static_export.write_queue_processing.start';

  public const WRITE_QUEUE_PROCESSING_END = 'static_export.write_queue_processing_end';

  public const WRITE_END = 'static_export.write_end';

  public const OUTPUT_END = 'static_export.output_end';

  public const END = 'static_export.end';

  public const CHAINED_STEP_END = 'static_export.chained_step_end';

}
