<?php

namespace Drupal\opigno_learning_path\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides a documentslastgroupblock block.
 *
 * @Block(
 *   id = "opigno_documents_last_group_block",
 *   admin_label = @Translation("DocumentsLastGroupBlock"),
 *   category = @Translation("Custom")
 * )
 */
class DocumentsLastGroupBlock extends BlockBase {

  /**
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|mixed
   */
  protected $groupId;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $this->groupId = $gid = $this->configuration["group"];
    $tid = _tft_get_group_tid($gid);
    $content = _tft_folder_content($tid, FALSE, $gid);
    $content = array_slice($content ?: [], 0, 4);
    foreach ($content as $index => $item) {
      $content[$index] = [
        '#theme' => 'opigno_documents_last_group_item',
        '#type' => $item["type"] == 'file' ? 'file' : 'folder',
        '#item' => $item,
        '#label' => $item["name"],
        '#link' => $this->itemLink($item),
      ];
    }
    $build['content'] = [
      '#theme' => 'opigno_documents_last_group_block',
      'content' => $content,
    ];
    return $build;
  }

  /**
   * Create a download link fr files adn view for folder.
   */
  public function itemLink($item) {
    if ($item["type"] == 'file') {
      $tft_url = Url::fromUri("internal:/tft/download/file/{$item['id']}")
        ->toString();
    }
    else {
      $tft_url = Url::fromRoute('tft.group', ['group' => $this->groupId], [
        'fragment' => "term/{$item['id']}",
      ])->toString();
    }
    return $tft_url;
  }

}
