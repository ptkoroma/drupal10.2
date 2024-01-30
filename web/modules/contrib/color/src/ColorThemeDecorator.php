<?php

namespace Drupal\color;

use Drupal\Core\Asset\CssOptimizer;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\Utility\Color;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Defines the color theme decorator service.
 */
class ColorThemeDecorator implements CacheTagsInvalidatorInterface {

  /**
   * The config factory interface.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  protected $configFactory;

  /**
   * The file system interface.
   *
   * @var Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The cache interface.
   *
   * @var Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The theme extension list service.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeExtensionList;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory interface.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system interface.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache interface.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FileSystemInterface $file_system, CacheBackendInterface $cache, ThemeExtensionList $themeExtensionList, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $file_system;
    $this->cache = $cache;
    $this->themeExtensionList = $themeExtensionList;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Invalidate tags.
   *
   * If library_info is invalidated, delete our generated files.
   *
   * @param array $tags
   *   The tags to invalidate.
   */
  public function invalidateTags(array $tags) {
    if (in_array('library_info', $tags)) {
      $this->unlinkGeneratedFiles();
    }
  }

  /**
   * Delete generated files.
   */
  public function unlinkGeneratedFiles() {
    $dirPath = 'public://color';
    if (is_dir($dirPath)) {
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
        $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
      }
    }
  }

  /**
   * Get theme files.
   *
   * @param string $theme
   *   The theme name.
   */
  public function getThemeFiles($theme) {
    $config = $this->configFactory->get('color.theme.' . $theme);
    $palette = $config->get('palette');
    if (empty($palette)) {
      return FALSE;
    }
    return $this->ensureFiles($theme, $palette, color_get_info($theme));
  }

  /**
   * Generate files if needed.
   *
   * @param string $theme
   *   The theme name.
   * @param array|null $palette
   *   The palette of color codes.
   * @param array $info
   *   Color info from the theme.
   *
   * @return array|null
   *   An array of paths information, if available.
   */
  public function ensureFiles($theme, $palette, $info) {
    if (!$palette) {
      return NULL;
    }
    $hash = self::getHash($theme);
    $cid = "color:paths:$hash";
    if ($data = $this->cache->get($cid)) {
      return $data->data;
    }

    // Prepare target locations for generated files.
    $paths['color'] = 'public://color';
    $paths['target'] = $paths['color'] . '/' . $hash;
    foreach ($paths as $path) {
      $this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY);
    }
    $paths['target'] = $paths['target'] . '/';
    $paths['id'] = $hash;
    $paths['source'] = $this->themeExtensionList->getPath($theme) . '/';
    $paths['files'] = $paths['map'] = [];

    // Copy over neutral images.
    foreach ($info['copy'] as $file) {
      $base = $this->fileSystem->basename($file);
      $source = $paths['source'] . $file;
      try {
        $filepath = $this->fileSystem->copy($source, $paths['target'] . $base);
      }
      catch (FileException $e) {
        $filepath = FALSE;
      }
      $paths['map'][$file] = $base;
      $paths['files'][] = $filepath;
    }

    // Render new images, if image has been provided.
    if (isset($info['base_image'])) {
      $this->renderImages($theme, $info, $paths, $palette);
    }

    // Rewrite theme stylesheets.
    $paths['css'] = [];
    foreach ($info['css'] as $stylesheet) {
      $source_css = $paths['source'] . $stylesheet;
      $target_css = $paths['target'] . $this->fileSystem->basename($stylesheet);
      if (file_exists($source_css)) {
        $css_optimizer = new CssOptimizer($this->fileUrlGenerator);
        // Aggregate @imports recursively for each configured top level CSS file
        // without optimization. Aggregation and optimization will be
        // handled by drupal_build_css_cache() only.
        $style = $css_optimizer->loadFile($source_css, FALSE);

        // Return the path to where this CSS file originated from, stripping
        // off the name of the file at the end of the path.
        $css_optimizer->rewriteFileURIBasePath = base_path() . dirname($source_css) . '/';

        // Prefix all paths within this CSS file, ignoring absolute paths.
        $style = preg_replace_callback('/url\([\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\)/i', [
          $css_optimizer,
          'rewriteFileURI',
        ], $style);

        // Rewrite stylesheet with new colors.
        $style = $this->rewriteStyleSheet($theme, $info, $paths, $palette, $style);
        $filepath = $this->fileSystem->saveData($style, $target_css, FileSystemInterface::EXISTS_REPLACE);
        $this->fileSystem->chmod($target_css);
      }
      $paths['css'][] = $target_css;
      $paths['files'][] = $filepath;
    }
    $this->cache->set($cid, $paths, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $paths;
  }

  /**
   * Get a hash that varies on theme and config cache contexts.
   *
   * @param string $theme
   *   The theme.
   *
   * @return string
   *   The hash.
   *
   * @todo
   *   Is it possible to replace "\Drupal::service('cache_contexts_manager')" to
   *   something else?.
   */
  public static function getHash($theme) {
    /** @var \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager */
    $cache_contexts_manager = \Drupal::service('cache_contexts_manager');
    $config = \Drupal::configFactory()->get('color.theme.' . $theme);
    $cache_contexts = array_merge(['theme'], $config->getCacheContexts());
    $cache_context_keys = $cache_contexts_manager->convertTokensToKeys($cache_contexts)->getKeys();
    $hash = hash('sha256', serialize($cache_context_keys));

    return $hash;
  }

  /**
   * Render images matching a given palette.
   *
   * @param string $theme
   *   The theme name.
   * @param array $info
   *   The theme info.
   * @param array $paths
   *   The theme file paths.
   * @param array $palette
   *   Theme color palette.
   */
  private function renderImages($theme, &$info, &$paths, $palette) {
    // Prepare template image.
    $source = $paths['source'] . '/' . $info['base_image'];
    $source = imagecreatefrompng($source);
    $width = imagesx($source);
    $height = imagesy($source);

    // Prepare target buffer.
    $target = imagecreatetruecolor($width, $height);
    imagealphablending($target, TRUE);

    // Fill regions of solid color.
    foreach ($info['fill'] as $color => $fill) {
      imagefilledrectangle($target, $fill[0], $fill[1], $fill[0] + $fill[2], $fill[1] + $fill[3], $this->gd($target, $palette[$color]));
    }

    // Render gradients.
    foreach ($info['gradients'] as $gradient) {
      // Get direction of the gradient.
      if (isset($gradient['direction']) && $gradient['direction'] == 'horizontal') {
        // Horizontal gradient.
        for ($x = 0; $x < $gradient['dimension'][2]; $x++) {
          $color = $this->blend($target, $palette[$gradient['colors'][0]], $palette[$gradient['colors'][1]], $x / ($gradient['dimension'][2] - 1));
          imagefilledrectangle($target, ($gradient['dimension'][0] + $x), $gradient['dimension'][1], ($gradient['dimension'][0] + $x + 1), ($gradient['dimension'][1] + $gradient['dimension'][3]), $color);
        }
      }
      else {
        // Vertical gradient.
        for ($y = 0; $y < $gradient['dimension'][3]; $y++) {
          $color = $this->blend($target, $palette[$gradient['colors'][0]], $palette[$gradient['colors'][1]], $y / ($gradient['dimension'][3] - 1));
          imagefilledrectangle($target, $gradient['dimension'][0], $gradient['dimension'][1] + $y, $gradient['dimension'][0] + $gradient['dimension'][2], $gradient['dimension'][1] + $y + 1, $color);
        }
      }
    }

    // Blend over template.
    imagecopy($target, $source, 0, 0, 0, 0, $width, $height);

    // Clean up template image.
    imagedestroy($source);

    // Cut out slices.
    foreach ($info['slices'] as $file => $coord) {
      list($x, $y, $width, $height) = $coord;
      $base = $this->fileSystem->basename($file);
      $image = $this->fileSystem->realpath($paths['target'] . $base);

      // Cut out slice.
      if ($file == 'screenshot.png') {
        $slice = imagecreatetruecolor(150, 90);
        imagecopyresampled($slice, $target, 0, 0, $x, $y, 150, 90, $width, $height);
        $this->configFactory->getEditable('color.theme.' . $theme)
          ->set('screenshot', $image)
          ->save();
      }
      else {
        $slice = imagecreatetruecolor($width, $height);
        imagecopy($slice, $target, 0, 0, $x, $y, $width, $height);
      }

      // Save image.
      imagepng($slice, $image);
      imagedestroy($slice);
      $paths['files'][] = $image;

      // Set standard file permissions for webserver-generated files.
      $this->fileSystem->chmod($image);

      // Build before/after map of image paths.
      $paths['map'][$file] = $base;
    }

    // Clean up target buffer.
    imagedestroy($target);
  }

  /**
   * Rewrites the stylesheet to match the colors in the palette.
   *
   * @param string $theme
   *   The theme name.
   * @param array $info
   *   The theme info.
   * @param array $paths
   *   Theme file paths.
   * @param array $palette
   *   Colors to be used.
   * @param string $style
   *   Style to be used.
   */
  private function rewriteStyleSheet($theme, &$info, $paths, $palette, $style) {
    // Prepare color conversion table.
    $conversion = $palette;
    foreach ($conversion as $k => $v) {
      $v = mb_strtolower($v);
      $conversion[$k] = Color::normalizeHexLength($v);
    }
    $default = $this->getPalette($theme, TRUE);

    // Split off the "Don't touch" section of the stylesheet.
    $split = "Color Module: Don't touch";
    if (strpos($style, $split) !== FALSE) {
      list($style, $fixed) = explode($split, $style);
    }

    // Find all colors in the stylesheet and the chunks in between.
    $style = preg_split('/(#[0-9a-f]{6}|#[0-9a-f]{3})/i', $style, -1, PREG_SPLIT_DELIM_CAPTURE);
    $is_color = FALSE;
    $output = '';
    $base = 'base';

    // Iterate over all the parts.
    foreach ($style as $chunk) {
      if ($is_color) {
        $chunk = mb_strtolower($chunk);
        $chunk = Color::normalizeHexLength($chunk);
        // Check if this is one of the colors in the default palette.
        if ($key = array_search($chunk, $default)) {
          $chunk = $conversion[$key];
        }
        // Not a pre-set color. Extrapolate from the base.
        else {
          $chunk = $this->shift($palette[$base], $default[$base], $chunk, $info['blend_target']);
        }
      }
      else {
        // Determine the most suitable base color for the next color.
        // 'a' declarations. Use link.
        if (preg_match('@[^a-z0-9_-](a)[^a-z0-9_-][^/{]*{[^{]+$@i', $chunk)) {
          $base = 'link';
        }
        // 'color:' styles. Use text.
        elseif (preg_match('/(?<!-)color[^{:]*:[^{#]*$/i', $chunk)) {
          $base = 'text';
        }
        // Reset back to base.
        else {
          $base = 'base';
        }
      }
      $output .= $chunk;
      $is_color = !$is_color;
    }
    // Append fixed colors segment.
    if (isset($fixed)) {
      $output .= $fixed;
    }

    // Replace paths to images.
    foreach ($paths['map'] as $before => $after) {
      $before = base_path() . $paths['source'] . $before;
      $before = preg_replace('`(^|/)(?!../)([^/]+)/../`', '$1', $before);
      $output = str_replace($before, $after, $output);
    }

    return $output;
  }

  /**
   * Retrieves the color palette for a particular theme.
   *
   * @param string $theme
   *   The theme name.
   * @param bool $default
   *   Boolean indicating if default palette should be returned.
   */
  public function getPalette($theme, $default = FALSE) {
    // Fetch and expand default palette.
    $info = color_get_info($theme);
    $palette = $info['schemes']['default']['colors'];

    if ($default) {
      return $palette;
    }

    // Load variable.
    // @todo Default color config should be moved to yaml in the theme.
    // Getting a mutable override-free object because this function is only used
    // in forms. Color configuration is used to write CSS to the file system
    // making configuration overrides pointless.
    return $this->configFactory->getEditable('color.theme.' . $theme)->get('palette') ?: $palette;
  }

  /**
   * Converts a hex triplet into a GD color.
   *
   * @param resource $img
   *   The image to process.
   * @param string $hex
   *   The color in hexadecimal format.
   */
  private function gd($img, $hex) {
    $c = array_merge([$img], $this->hexToRgb($hex));

    return call_user_func_array('imagecolorallocate', $c);
  }

  /**
   * Blends two hex colors and returns the GD color.
   *
   * @param resource $img
   *   The image to process.
   * @param string $hex1
   *   The color in hexadecimal format.
   * @param string $hex2
   *   The color in hexadecimal format.
   * @param string $alpha
   *   The alpha.
   */
  private function blend($img, $hex1, $hex2, $alpha) {
    $in1 = $this->hexToRgb($hex1);
    $in2 = $this->hexToRgb($hex2);
    $out = [$img];
    for ($i = 0; $i < 3; ++$i) {
      $out[] = $in1[$i] + ($in2[$i] - $in1[$i]) * $alpha;
    }

    return call_user_func_array('imagecolorallocate', $out);
  }

  /**
   * Converts an RGB triplet to a hex color.
   *
   * @param string $rgb
   *   The color in RGB format.
   * @param bool $normalize
   *   If color should be normalized.
   */
  public function rgbToHex($rgb, $normalize = FALSE) {
    $out = 0;
    foreach ($rgb as $k => $v) {
      $out |= (($v * ($normalize ? 255 : 1)) << (16 - $k * 8));
    }

    return '#' . str_pad(dechex($out), 6, 0, STR_PAD_LEFT);
  }

  /**
   * Converts a hex color into an RGB triplet.
   *
   * @param string $hex
   *   The color in hex format.
   * @param bool $normalize
   *   If color should be normalized.
   */
  public function hexToRgb($hex, $normalize = FALSE) {
    $hex = substr($hex, 1);
    if (strlen($hex) == 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $c = hexdec($hex);
    for ($i = 16; $i >= 0; $i -= 8) {
      $out[] = (($c >> $i) & 0xFF) / ($normalize ? 255 : 1);
    }

    return $out;
  }

  /**
   * Shifts a given color, using a reference pair and a target blend color.
   *
   * Note: this function is significantly different from the JS version, as it
   * is written to match the blended images perfectly.
   *
   * Constraint: if (ref2 == target + (ref1 - target) * delta) for some fraction
   * delta then (return == target + (given - target) * delta).
   *
   * Loose constraint: Preserve relative positions in saturation and luminance
   * space.
   *
   * @param string $given
   *   The given color.
   * @param string $ref1
   *   The reference pair.
   * @param string $ref2
   *   The reference pair.
   * @param string $target
   *   A target blend color.
   */
  private function shift($given, $ref1, $ref2, $target) {
    // We assume that ref2 is a blend of ref1 and target and find
    // delta based on the length of the difference vectors.
    // Delta = 1 - |ref2 - ref1| / |white - ref1|.
    $target = $this->hexToRgb($target, TRUE);
    $ref1 = $this->hexToRgb($ref1, TRUE);
    $ref2 = $this->hexToRgb($ref2, TRUE);
    $numerator = 0;
    $denominator = 0;
    for ($i = 0; $i < 3; ++$i) {
      $numerator += ($ref2[$i] - $ref1[$i]) * ($ref2[$i] - $ref1[$i]);
      $denominator += ($target[$i] - $ref1[$i]) * ($target[$i] - $ref1[$i]);
    }
    $delta = ($denominator > 0) ? (1 - sqrt($numerator / $denominator)) : 0;

    // Calculate the color that ref2 would be if the assumption was true.
    for ($i = 0; $i < 3; ++$i) {
      $ref3[$i] = $target[$i] + ($ref1[$i] - $target[$i]) * $delta;
    }

    // If the assumption is not true, there is a difference between ref2 and ref3.
    // We measure this in HSL space. Notation: x' = hsl(x).
    $ref2 = $this->rgbToHsl($ref2);
    $ref3 = $this->rgbToHsl($ref3);
    for ($i = 0; $i < 3; ++$i) {
      $shift[$i] = $ref2[$i] - $ref3[$i];
    }

    // Take the given color, and blend it towards the target.
    $given = $this->hexToRgb($given, TRUE);
    for ($i = 0; $i < 3; ++$i) {
      $result[$i] = $target[$i] + ($given[$i] - $target[$i]) * $delta;
    }

    // Finally, we apply the extra shift in HSL space.
    // Note: if ref2 is a pure blend of ref1 and target, then |shift| = 0.
    $result = $this->rgbToHsl($result);
    for ($i = 0; $i < 3; ++$i) {
      $result[$i] = min(1, max(0, $result[$i] + $shift[$i]));
    }
    $result = $this->hslToRgb($result);

    // Return hex color.
    return $this->rgbToHex($result, TRUE);
  }

  /**
   * Converts an RGB triplet to HSL.
   *
   * @param array $rgb
   *   Color to be processed.
   */
  private function rgbToHsl(array $rgb) {
    $r = $rgb[0];
    $g = $rgb[1];
    $b = $rgb[2];
    $min = min($r, min($g, $b));
    $max = max($r, max($g, $b));
    $delta = $max - $min;
    $l = ($min + $max) / 2;
    $s = 0;

    if ($l > 0 && $l < 1) {
      $s = $delta / ($l < 0.5 ? (2 * $l) : (2 - 2 * $l));
    }

    $h = 0;
    if ($delta > 0) {
      if ($max == $r && $max != $g) {
        $h += ($g - $b) / $delta;
      }
      if ($max == $g && $max != $b) {
        $h += (2 + ($b - $r) / $delta);
      }
      if ($max == $b && $max != $r) {
        $h += (4 + ($r - $g) / $delta);
      }
      $h /= 6;
    }

    return [$h, $s, $l];
  }

  /**
   * Converts an HSL triplet into RGB.
   *
   * @param array $hsl
   *   HSL triplet.
   */
  private function hslToRgb(array $hsl) {
    $h = $hsl[0];
    $s = $hsl[1];
    $l = $hsl[2];
    $m2 = ($l <= 0.5) ? $l * ($s + 1) : $l + $s - $l * $s;
    $m1 = $l * 2 - $m2;

    return [
      $this->hueToRgb($m1, $m2, $h + 0.33333),
      $this->hueToRgb($m1, $m2, $h),
      $this->hueToRgb($m1, $m2, $h - 0.33333),
    ];
  }

  /**
   * Helper function for ::hueToRgb().
   *
   * @param int $m1
   *   The m1.
   * @param int $m2
   *   The m2.
   * @param int $h
   *   The hueToRgb.
   */
  private function hueToRgb($m1, $m2, $h) {
    $h = ($h < 0) ? $h + 1 : (($h > 1) ? $h - 1 : $h);
    if ($h * 6 < 1) {
      return $m1 + ($m2 - $m1) * $h * 6;
    }
    if ($h * 2 < 1) {
      return $m2;
    }
    if ($h * 3 < 2) {
      return $m1 + ($m2 - $m1) * (0.66666 - $h) * 6;
    }

    return $m1;
  }

}
