<?php

namespace Drupal\indieweb_identity\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides an 'IndieWeb H-Card' block.
 *
 * @Block(
 * id = "indieweb_identity_hcard",
 * admin_label = @Translation("IndieWeb H-Card"),
 * category = @Translation("Custom")
 * )
 */
class HCardBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new HCardBlock instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RequestStack $requestStack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('file_url_generator'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
 /**
     * {@inheritdoc}
     */
    public function build(): array {
        $config = $this->configFactory->get('indieweb_identity.settings');

        // Since we now store links as an array, no parsing is required.
        // We just ensure it defaults to an empty array if nothing is set.
        $social_links = $config->get('social_links') ?: [];

        // Handle avatar URL using injected FileUrlGenerator.
        $avatarUrl = '';
        $avatar_data = $config->get('avatar');
        if (!empty($avatar_data) && is_array($avatar_data)) {
            $fid = reset($avatar_data);
            $file = File::load($fid);
            if ($file) {
                // Generates absolute URL for external IndieWeb validators.
                $avatarUrl = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            }
        }

        // Get the site URL from injected RequestStack.
        $site_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

        return [
            '#type' => 'component',
            '#component' => 'indieweb_identity:h-card',
            '#props' => [
                'name'         => $config->get('name'),
                'url'          => $site_url,
                'avatar'       => $avatarUrl,
                'bio'          => $config->get('bio'),
                'social_links' => $social_links,
                'display_mode' => 'full',
                'hidden'       => (bool) $config->get('hidden'),
            ],
        ];
    }


  /**
   * Sniffs the URL to return a FontAwesome class.
   */
  private function getSocialIcon(string $url): string {
    $icons = [
      'bsky.app' => 'fa-brands fa-bluesky',
	  'facebook.com' => 'fa-brands fa-facebook',    
      'github.com' => 'fa-brands fa-github',
      'drupal.org' => 'fa-brands fa-drupal'
    ];

    foreach ($icons as $domain => $class) {
      if (str_contains($url, $domain)) {
        return $class;
      }
    }
    return 'fa-solid fa-link';
  }

}