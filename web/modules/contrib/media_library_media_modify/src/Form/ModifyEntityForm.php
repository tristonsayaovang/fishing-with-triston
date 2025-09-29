<?php

namespace Drupal\media_library_media_modify\Form;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media_library_media_modify\EntityReferenceOverrideService;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\PrivateKey;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Implements an example form.
 */
class ModifyEntityForm extends FormBase {

  /**
   * The private temp store service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity reference override service.
   *
   * @var \Drupal\media_library_media_modify\EntityReferenceOverrideService
   */
  protected $entityReferenceOverrideService;

  /**
   * The private key service.
   *
   * @var \Drupal\Core\PrivateKey
   */
  protected $privateKey;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $form = parent::create($container);
    $form->setPrivateTempStore($container->get('tempstore.private'));
    $form->setEntityTypeManager($container->get('entity_type.manager'));
    $form->setEntityFieldManager($container->get('entity_field.manager'));
    $form->setEntityReferenceOverrideService($container->get('media_library_media_modify'));
    $form->setPrivateKey($container->get('private_key'));
    return $form;
  }

  /**
   * Set the temp store service.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The temp store service.
   */
  protected function setPrivateTempStore(PrivateTempStoreFactory $privateTempStoreFactory): void {
    $this->tempStore = $privateTempStoreFactory->get('media_library_media_modify');
  }

  /**
   * Set the entity type manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  protected function setEntityTypeManager(EntityTypeManagerInterface $entityTypeManager): void {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Set the entity field manager service.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  protected function setEntityFieldManager(EntityFieldManagerInterface $entityFieldManager): void {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * Set the entity reference override service.
   *
   * @param \Drupal\media_library_media_modify\EntityReferenceOverrideService $entityReferenceOverrideService
   *   The entity reference override service.
   */
  protected function setEntityReferenceOverrideService(EntityReferenceOverrideService $entityReferenceOverrideService): void {
    $this->entityReferenceOverrideService = $entityReferenceOverrideService;
  }

  /**
   * Set the private key service.
   *
   * @param \Drupal\Core\PrivateKey $privateKey
   *   The private key service.
   */
  protected function setPrivateKey(PrivateKey $privateKey): void {
    $this->privateKey = $privateKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'override_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $store_entry = []) {
    if (empty($store_entry)) {
      $hash = $this->getRequest()->query->get('hash');
      $store_entry = $this->tempStore->get($hash);
    }
    else {
      $hash = Crypt::hmacBase64($store_entry['field_widget_id'], Settings::getHashSalt() . $this->privateKey->get());
      $this->tempStore->set($hash, $store_entry);
      // Add the hash to the query for later access checking.
      $this->getRequest()->query->set('hash', $hash);
    }
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];
    $form_mode = $store_entry['form_mode'];
    $referencing_entity_type_id = $store_entry['referencing_entity_type_id'];

    $form['#attached']['library'][] = 'media_library_media_modify/form';

    // @todo Remove the ID when we can use selectors to replace content via
    //   AJAX in https://www.drupal.org/project/drupal/issues/2821793.
    $form['#prefix'] = '<div id="entity-reference-override-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];

    $referencing_entity_type_label = $this->entityTypeManager->getDefinition($referencing_entity_type_id)->getSingularLabel();
    $form['help_text'] = [
      '#type' => 'item',
      '#markup' => $this->t('All changes made in here, only apply to this %entity_type that is referenced in the context of the parent %referencing_entity_type_label.', [
        '%entity_type' => $referenced_entity->getEntityType()->getSingularLabel(),
        '%referencing_entity_type_label' => $referencing_entity_type_label,
      ]),
      '#weight' => -1,
    ];

    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    if ($form_display->isNew()) {
      $this->messenger()->addWarning($this->t('Form display mode %form_mode does not exists.', ['%form_mode' => $form_display->id()]));
      return $form;
    }
    // Indicator for other modules that this is a modify-form.
    $form_state->set('media_library_media_modify', TRUE);
    $form_display->buildForm($referenced_entity, $form, $form_state);
    foreach (Element::children($form) as $key) {
      // Entity keys can be displayed, but are not overridable.
      $entity_type_keys = $referenced_entity->getEntityType()->getKeys();
      if (in_array($key, $entity_type_keys)) {
        $form[$key]['#disabled'] = TRUE;
      }
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'url' => Url::fromRoute('media_library_media_modify.form'),
        'options' => [
          'query' => $this->getRequest()->query->all() + [
            'hash' => $hash,
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Get overwrite form display for the referenced entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity
   *   The referenced entity.
   * @param string $form_mode
   *   The form mode of the display.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The overwrite form display.
   */
  protected function getFormDisplay(FieldableEntityInterface $referenced_entity, string $form_mode): EntityFormDisplayInterface {
    $form_display = EntityFormDisplay::collectRenderDisplay($referenced_entity, $form_mode);
    $definitions = $this->entityFieldManager->getFieldDefinitions($referenced_entity->getEntityTypeId(), $referenced_entity->bundle());
    foreach ($form_display->getComponents() as $name => $component) {
      if (!empty($definitions[$name]) && !$definitions[$name]->isDisplayConfigurable('form')) {
        $form_display->removeComponent($name);
      }
    }
    return $form_display;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * The access function.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function access(AccountInterface $account) {
    $hash = \Drupal::request()->query->get('hash');
    /** @var \Drupal\Core\TempStore\PrivateTempStore $temp_store */
    $temp_store = \Drupal::service('tempstore.private')->get('media_library_media_modify');
    if (!($store_entry = $temp_store->get($hash))) {
      return AccessResult::forbidden();
    }

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];

    return $referenced_entity->access('view', $account, TRUE);
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or represents a
   *   successful submission.
   */
  public function ajaxSubmit(array $form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#entity-reference-override-form-wrapper', $form));
      return $response;
    }

    $hash = $this->getRequest()->query->get('hash');

    $store_entry = $this->tempStore->get($hash);
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity */
    $referenced_entity = $store_entry['referenced_entity'];
    $form_mode = $store_entry['form_mode'];
    $field_widget_id = $store_entry['field_widget_id'];

    /** @var \Drupal\Core\Entity\FieldableEntityInterface $original_entity */
    $original_entity = $this->entityTypeManager->getStorage($referenced_entity->getEntityTypeId())->load($referenced_entity->id());

    $form_display = $this->getFormDisplay($referenced_entity, $form_mode);
    $extracted_fields = $form_display->extractFormValues($referenced_entity, $form, $form_state);

    $values = $this->entityReferenceOverrideService->getOverriddenValues($referenced_entity, $original_entity, $extracted_fields);

    $response
      ->addCommand(new InvokeCommand("[data-entity-reference-override-value=\"$field_widget_id\"]", 'val', [Json::encode($values)]))
      ->addCommand(new CloseDialogCommand());

    return $response;
  }

}
