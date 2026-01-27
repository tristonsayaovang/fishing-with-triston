<?php

namespace Drupal\fwt_lure_suggestor\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\fwt_lure_suggestor\Service\LureSuggestorAlgorithmService;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Hello' Block.
 */

#[Block(
  id: "lure_suggestor_block",
  admin_label: new TranslatableMarkup("Fishing with Triston Lure Suggestor"),
  category: new TranslatableMarkup("Fishing with Triston Lure Suggestor")
)]

class LureSuggestorBlock extends BlockBase  implements ContainerFactoryPluginInterface
{
  private $lureSuggestion;

  public  function __construct(array $configuration, $plugin_id, $plugin_definition, LureSuggestorAlgorithmService $lureSuggestor)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->lureSuggestion = $lureSuggestor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('fwt_lure_suggestor.lure_suggestor_algorithm_service')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function build()
  {
    return [
      '#theme' => 'fwt_lure_suggestor',
      '#testMessage' => 'test',
      '#weather' =>$this->lureSuggestion->getWeather(),
    ];
  }
}
