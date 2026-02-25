<?php

declare(strict_types=1);

namespace Drupal\indieweb_identity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface; 
use Drupal\Component\Utility\EmailValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Configure Indieweb identity settings for this site.
 */
final class HCardSettingsForm extends ConfigFormBase {

  /**
     * Constructs an HCardSettingsForm object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     * The factory for configuration objects.
     * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
     * The typed config manager.
     * @param \Drupal\Component\Utility\EmailValidatorInterface $emailValidator
     * The email validator service.
     */
   public function __construct(
        ConfigFactoryInterface $config_factory,
        protected TypedConfigManagerInterface $typedConfigManager,
        protected EmailValidatorInterface $emailValidator,
        RequestStack $requestStack // No 'protected' here because it exists in parent
    ) {
        parent::__construct($config_factory, $typedConfigManager);
        
        // Manually assign it to the property inherited from FormBase
        $this->requestStack = $requestStack;
    }
    
    
   /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('config.factory'),
            $container->get('config.typed'),
            $container->get('email.validator'),
            $container->get('request_stack')
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function getFormId(): string {
        return 'indieweb_identity_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array {
        return ['indieweb_identity.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('indieweb_identity.settings');
        $existing_links = $config->get('social_links');

		 $form['intro'] = [
			'#type' => 'item',
			'#markup' => '<h2>' . $this->t('IndieWeb Identity Settings') . '</h2>' .
				'<p>' . $this->t('Use this form to define your <strong>Representative h-card</strong>. This information is used to identify you across the IndieWeb, providing a machine-readable "business card" for services like Bridgy, IndieAuth, and Webmentions.') . '</p>' .
				'<p>' . $this->t('By providing your site URL and social profile links (with rel="me"), you establish <strong>sovereign identity</strong>, proving that you are the same person across different platforms.') . '</p>',
		];



        $form['name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Full Name'),
            '#default_value' => $config->get('name'),
            '#required' => TRUE,
        ];

        $form['nickname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Nickname'),
            '#default_value' => $config->get('nickname'),
            '#description' => $this->t('This will receive the p-nickname class.'),
        ];

        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email Address'),
            '#default_value' => $config->get('email'),
            '#description' => $this->t('This will receive the u-email class.'),
        ];

        $form['avatar'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Avatar Image'),
            '#upload_location' => 'public://identity/',
            '#default_value' => $config->get('avatar'),
        ];

        $form['bio'] = [
            '#type' => 'textarea',
            '#title' => $this->t('Bio / Note'),
            '#default_value' => $config->get('bio'),
        ];

        $form['hidden'] = [
            '#type'  => 'checkbox',
            '#title' => $this->t('Hidden'),
            '#description' => $this->t('Should the h-card be visually hidden?'),
            '#default_value' => $config->get('hidden') ?? TRUE,
        ];

        // --- Social Links Wrapper ---
        if (!$form_state->has('num_links')) {
            $count = is_array($existing_links) ? count($existing_links) : 0;
            $form_state->set('num_links', max($count, 1));
        }
        $num_links = $form_state->get('num_links');

        $form['social_links_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'social-links-wrapper'],
            '#tree' => TRUE,
        ];

        for ($i = 0; $i < $num_links; $i++) {
            $form['social_links_wrapper'][$i] = [
                '#type' => 'details',
                '#title' => $this->t('Social Link #@num', ['@num' => $i + 1]),
                '#open' => TRUE,
                '#attributes' => ['class' => ['container-inline']],
            ];

            $form['social_links_wrapper'][$i]['title'] = [
                '#type' => 'textfield',
                '#title' => $this->t('Title'),
                '#default_value' => $existing_links[$i]['title'] ?? '',
                '#size' => 20,
            ];

            $form['social_links_wrapper'][$i]['url'] = [
                '#type' => 'textfield',
                '#title' => $this->t('URL'),
                '#default_value' => $existing_links[$i]['url'] ?? '',
                '#size' => 40,
            ];
        }

        $form['actions']['add_link'] = [
            '#type' => 'submit',
            '#value' => $this->t('Add another link'),
            '#submit' => ['::addOne'],
            '#ajax' => [
                'callback' => '::addMoreCallback',
                'wrapper' => 'social-links-wrapper',
            ],
            '#limit_validation_errors' => [],
        ];

        return parent::buildForm($form, $form_state);
    }

    public function addMoreCallback(array &$form, FormStateInterface $form_state) {
        return $form['social_links_wrapper'];
    }

    public function addOne(array &$form, FormStateInterface $form_state) {
        $num_links = $form_state->get('num_links');
        $form_state->set('num_links', $num_links + 1);
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        // Email Sanity Check.
        $email = trim($form_state->getValue('email') ?? '');
        if ($email !== '' && !$this->emailValidator->isValid($email)) {
            $form_state->setErrorByName('email', $this->t('The email address %mail is not valid.', ['%mail' => $email]));
        }

        // Social Links Validation.
        $links = $form_state->getValue('social_links_wrapper');
        if (!empty($links) && is_array($links)) {
            foreach ($links as $delta => $link) {
                $url = trim($link['url'] ?? '');
                $title = trim($link['title'] ?? '');
                if ($url !== '') {
                    if ($title === '') {
                        $form_state->setErrorByName("social_links_wrapper][$delta][title", $this->t('Link #@num: Please provide a title.', ['@num' => $delta + 1]));
                    }
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        $form_state->setErrorByName("social_links_wrapper][$delta][url", $this->t('Link #@num: Invalid URL.', ['@num' => $delta + 1]));
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('indieweb_identity.settings');
        
        // Handle Avatar.
        $new_fid = $form_state->getValue('avatar')[0] ?? NULL;
        if ($new_fid) {
            $file = \Drupal\file\Entity\File::load($new_fid);
            if ($file) {
                $file->setPermanent();
                $file->save();
            }
        }

        // Process Links.
        $submitted_links = $form_state->getValue('social_links_wrapper') ?: [];
        $processed_links = [];
        foreach ($submitted_links as $link) {
            if (!empty(trim($link['url'] ?? ''))) {
                $processed_links[] = [
                    'title' => trim($link['title'] ?? ''),
                    'url' => trim($link['url'] ?? ''),
                ];
            }
        }

        $config
            ->set('name', $form_state->getValue('name'))
            ->set('nickname', $form_state->getValue('nickname'))
            ->set('email', $form_state->getValue('email'))
            ->set('avatar', $form_state->getValue('avatar'))
            ->set('bio', $form_state->getValue('bio'))
            ->set('hidden', $form_state->getValue('hidden'))
            ->set('social_links', $processed_links)
            ->save();

 		$host = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
        $validator_url = "https://indiewebify.me/validate-h-card/?url=" . urlencode($host);

        $this->messenger()->addStatus($this->t('<strong>Next Step:</strong> Ensure the "IndieWeb H-Card" block is placed in a region on your <a href=":home">home page</a>.', [
            ':home' => $host,
        ]));

        $this->messenger()->addStatus($this->t('Once the block is visible, you can <a href=":url" target="_blank" rel="noopener">run the IndieWebify Validator</a>.', [
            ':url' => $validator_url,
        ]));
        
        parent::submitForm($form, $form_state);
    }
}