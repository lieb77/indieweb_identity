<?php

declare(strict_types=1);

namespace Drupal\indieweb_identity\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Implement hooks per Drupal 11 specs.
 */
class ModuleHooks {

/**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.indieweb_identity':
        $output  = "<h2>" . t('IndieWeb Identity Help') . "</h2>";
        $output .= "<p>" . t('This module provides a centralized way to manage your <strong>Representative h-card</strong>—the machine-readable identity that tells the IndieWeb who you are.') . "</p>";
        $output .= "<h3>" . t('Configuration') . "</h3>";
        $output .= "<ul>";
        $output .= "<li>" . t('Set your name, bio, and social links at the <a href=":settings">Identity Settings</a> page.', [':settings' => Url::fromRoute('indieweb_identity.settings')->toString()]) . "</li>";
        $output .= "<li>" . t('Upload an avatar to be used as your <em>u-photo</em>.') . "</li>";
        $output .= "<li>" . t('Provide URLs to your other profiles (Bluesky, GitHub, etc.) to establish <em>rel="me"</em> verification.') . "</li>";
        $output .= "</ul>";
        $output .= "<h3>" . t('Usage') . "</h3>";
        $output .= "<p>" . t('Once configured, you must place the <strong>IndieWeb H-Card block</strong> on your homepage using the <a href=":blocks">Block Layout</a>. The h-card is hidden from humans by default but remains visible to parsers and validators.', [':blocks' => Url::fromRoute('block.admin_display')->toString()]) . "</p>";
        
        return $output;
    }
  }

  // End of class.
}
