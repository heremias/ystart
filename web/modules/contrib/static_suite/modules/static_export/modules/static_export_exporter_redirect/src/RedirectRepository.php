<?php

namespace Drupal\static_export_exporter_redirect;

use Drupal\Core\Database\Connection;

/**
 * A redirect repository from Static Export redirect exporter.
 */
class RedirectRepository implements RedirectRepositoryInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructs a RedirectRepository object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function findAll(): array {
    $redirects = [];

    $query = $this->connection->query('SELECT * FROM {redirect} ORDER BY created DESC');
    if ($query) {
      $redirects = $query->fetchAll();
    }
    foreach ($redirects as $key => $redirect) {
      $redirects[$key] = (array) $redirect;
    }

    return $redirects;
  }

}
