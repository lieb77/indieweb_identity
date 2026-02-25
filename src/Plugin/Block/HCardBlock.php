<?php

declare(strict_types=1);

namespace Drupal\indieweb_identity\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
        string $plugin_id,
        mixed $plugin_definition,
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
    public function build(): array {
        $config = $this->configFactory->get('indieweb_identity.settings');

        // We use the array directly from config.
        $social_links = $config->get('social_links') ?: [];

        // Handle avatar URL.
        $avatarUrl = '';
        $avatar_data = $config->get('avatar');
        if (!empty($avatar_data) && is_array($avatar_data)) {
            $fid = reset($avatar_data);
            $file = File::load($fid);
            // Auditor fix: Ensure we actually have a file object before using it.
            if ($file instanceof File) {
                // Generates absolute URL for external IndieWeb validators.
                $avatarUrl = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            }
        }

        // Get the site URL.
        $site_url = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();

        return [
            '#type' => 'component',
            '#component' => 'indieweb_identity:h-card',
            '#props' => [
                'name'         => $config->get('name'),
                'url'          => $site_url,
                'avatar'       => $avatarUrl,
                'bio'          => $config->get('bio'),
                'nickname'     => $config->get('nickname'), 
		        'email'        => $config->get('email'),    
                'social_links' => $social_links,
                'display_mode' => 'full',
                'hidden'       => (bool) $config->get('hidden'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheTags(): array {
        return Cache::mergeTags(parent::getCacheTags(), [
            'config:indieweb_identity.settings',
        ]);
    }

}