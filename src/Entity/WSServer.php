<?php

namespace Drupal\wsdata\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Web Service Server entity.
 *
 * @ConfigEntityType(
 *   id = "wsserver",
 *   label = @Translation("Web Service Server"),
 *   handlers = {
 *     "list_builder" = "Drupal\wsdata\WSServerListBuilder",
 *     "form" = {
 *       "add" = "Drupal\wsdata\Form\WSServerForm",
 *       "edit" = "Drupal\wsdata\Form\WSServerForm",
 *       "delete" = "Drupal\wsdata\Form\WSServerDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\wsdata\WSServerHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "wsserver",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/wsserver/{wsserver}",
 *     "add-form" = "/admin/structure/wsserver/add",
 *     "edit-form" = "/admin/structure/wsserver/{wsserver}/edit",
 *     "delete-form" = "/admin/structure/wsserver/{wsserver}/delete",
 *     "collection" = "/admin/structure/wsserver"
 *   }
 * )
 */
class WSServer extends ConfigEntityBase implements WSServerInterface {
  static $WSCONFIG_DEFAULT_DEGRADED_BACKOFF = 900;

  /**
   * The Web Service Server ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Web Service Server label.
   *
   * @var string
   */
  protected $label;

  public $endpoint;
  public $wsconnector;
  public $settings;
  public $wsconnectorInst;

  protected $state;
  protected $languagehandling;

  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
  	$wsconnectorman = \Drupal::service('plugin.manager.wsconnector');
  	$wscdefs = $wsconnectorman->getDefinitions();
  	if (isset($wscdefs[$this->wsconnector])) {
      $this->wsconnectorInst = $wsconnectorman->createInstance($this->wsconnector);
      $this->wsconnectorInst->setEndpoint($this->endpoint);
  	}
  	$drupalstate = \Drupal::state();
  	$this->state = $drupalstate->get('wsdata.wsserver.' . $this->id, array());
  }

  public function __destruct() {
  	$drupalstate = \Drupal::state();
  	$drupalstate->set('wsdata.wsserver.' . $this->id, $this->state);
  }

  /**
   * Return types of methods supported by the connector.
   */
  public function getMethods() {
    return $this->wsconnectorInst->getMethods();
  }

  public function getDefaultMethod() {
    $methods = array_keys($this->getMethods());
    return reset($methods);
  }

  /**
   * Return supported languageplugins.
   */
  public function getEnabledLanguagePlugin() {
  	return ['default'];
  }

  /**
   * Set the endpoint.
   */
  public function setEndpoint($endpoint) {
    $this->endpoint = $endpoint;
  }

  /**
   * Get the endpoint.
   */
  public function getEndpoint() {
    return $this->endpoint;
  }

  /**
   * Disabled the wsserver.
   */
  public function disable($degraded = FALSE) {
    $reason = '';

    if ($degraded) {
      if (!isset($this->state['degraded_backoff'])) {
        $this->state['degraded_backoff'] = wsserver::$WSCONFIG_DEFAULT_DEGRADED_BACKOFF;
      }
      if ($this->state['degraded_backoff'] == 0) {
        return;
      }

      $reason = '  ' . t('Automatically disabled due to degrated service.');
      $this->state['degraded'] = time();
    }

    $this->state['disabled'] = TRUE;
    \Drupal::logger('wsdata')->warning(t('WSServer %label (%type) was disabled.', array('%label' => $this->label(), '%type' => $this->wsconnector)) . $reason);
  }

  /**
   * Enable the wsserver
   */
  public function enable($degraded = FALSE) {
    unset($this->state['degraded']);
    unset($this->state['disabled']);

    $reason = '';
    if ($degraded) {
      $reason = '  ' . t('Automatically re-enabling previously degrated service.');
    }

    \Drupal::logger('wsdata')->notice( t('WSConfig Type %label (%type) was enabled.', array('%label' => $this->label(), '%type' => $this->wsconnector)) . $reason);
  }

  /**
   * Check if wsserver is disabled.
   */
  public function isDisabled() {
  	if (!isset($this->state['degraded_backoff'])) {
      $this->state['degraded_backoff'] = wsserver::$WSCONFIG_DEFAULT_DEGRADED_BACKOFF;
    }

    if (isset($this->state['degraded']) and $this->state['degraded'] < time() - $this->state['degraded_backoff']) {
      $this->enable(TRUE);
      return FALSE;
    }

    return isset($this->state['disabled']) ? $this->state['disabled'] : FALSE;
  }

  /**
   * Cause the WSServer to become degraded.
   */
  public function getDegraded() {
    if (!isset($this->state['degraded_backoff'])) {
      $this->state['degraded_backoff'] = wsserver::$WSCONFIG_DEFAULT_DEGRADED_BACKOFF;
    }

    if (isset($this->state['degraded'])) {
      return $this->state['degraded'] - time() + $this->state['degraded_backoff'];
    }

    return 0;
  }

  public function getConnector() {
    return $this->wsconnectorInst;
  }
}
