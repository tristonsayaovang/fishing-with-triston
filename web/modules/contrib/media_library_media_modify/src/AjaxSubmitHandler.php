<?php

namespace Drupal\media_library_media_modify;

use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;

class AjaxSubmitHandler {

  //phpcs:ignore
  use AjaxFormHelperTrait {
    isAjax as public;
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand());
    return $response;
  }

}
