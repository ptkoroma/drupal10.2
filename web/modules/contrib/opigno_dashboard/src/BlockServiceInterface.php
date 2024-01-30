<?php

namespace Drupal\opigno_dashboard;

/**
 * The Opigno dashboard block service definition.
 */
interface BlockServiceInterface {

  /**
   * Returns all blocks.
   *
   * @return array
   *   The list of all block definitions.
   */
  public function getAllBlocks(): array;

  /**
   * Returns available blocks.
   *
   * @return array
   *   The list of available blocks.
   */
  public function getAvailableBlocks(): array;

  /**
   * Returns blocks contents.
   *
   * @return array
   *   The list of dashboard blocks to be rendered.
   */
  public function getDashboardBlocksContents($need_render = TRUE): array;

  /**
   * Creates blocks instances.
   */
  public function createBlocksInstances(): void;

  /**
   * Sanitizes ID string.
   *
   * @param string $id
   *   The block ID to be sanitized.
   *
   * @return string
   *   The sanitized block ID.
   */
  public function sanitizeId(string $id): string;

  /**
   * Sanitizes ID string for legacy blocks.
   *
   * @param string $id
   *   The block ID to be sanitized.
   *
   * @return string
   *   The sanitized block ID.
   */
  public function sanitizeIdOld(string $id): string;

  /**
   * Check if social features enabled on site or not.
   *
   * @return bool
   *   Whether social features enabled on site or not.
   */
  public function isSocialFeatureEnabled(): bool;

  /**
   * Get the default dashboard configuration.
   *
   * @return string
   *   The default dashboard configuration.
   */
  public function getDefaultDashboardConfig(): string;

  /**
   * The ID of default dashboard layout.
   *
   * @return int
   *   The ID of default dashboard layout.
   */
  public function getDefaultLayoutId(): int;

  /**
   * Returns positioning.
   *
   * @param int|string|null $uid
   *   The user ID to get the positioning for.
   * @param bool $default
   *   Should the default positioning be used or not.
   * @param bool $user_default
   *   Should the user default positioning be used or not.
   *
   * @return array|\Symfony\Component\HttpFoundation\JsonResponse
   *   Blocks positioning.
   */
  public function getPositioning($uid = NULL, bool $default = FALSE, bool $user_default = FALSE);

}
