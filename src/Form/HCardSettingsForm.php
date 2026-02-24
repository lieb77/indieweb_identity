<?php

declare(strict_types=1);

namespace Drupal\indieweb_identity\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Configure Indieweb identity settings for this site.
 */
final class HCardSettingsForm extends ConfigFormBase {

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
			'#description' => $this->t("This will receive the p-note class, and be wrapped in a link to this site's front page with a u-url class."),
			'#required' => TRUE,
		];
	
		$form['avatar'] = [
			'#type' => 'managed_file',
			'#title' => $this->t('Avatar Image'),
			'#upload_location' => 'public://identity/',
			'#default_value' => $config->get('avatar'),
			'#description' => $this->t('This will receive the u-photo class.'),
			
		];
	
		$form['bio'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Bio / Note'),
			'#default_value' => $config->get('bio'),
			'#description' => $this->t('This will receive the  p-note class.'),
		];
		
			$form['hidden'] = [
			'#type'  => 'checkbox',
			'#title' => $this->t('Hidden'),
			'#description' => $this->t('Should the h-card be visually hidden?'),
			'#default_value' => $config->get('hidden')      
		];
	
		// ---------------- Social links
		// 1. Initialize the row count from saved config or state.
        if (!$form_state->has('num_links')) {
            $count = is_array($existing_links) ? count($existing_links) : 0;
            $form_state->set('num_links', max($count, 1));
        }
        $num_links = $form_state->get('num_links');

        // 2. The AJAX wrapper.
        $form['social_links_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'social-links-wrapper'],
            '#tree' => TRUE,
        ];

        // 3. Build the rows.
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

        // 4. The Add Button.
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

    /**
     * Reverted simple Add handler.
     */
    public function addOne(array &$form, FormStateInterface $form_state) {
        $num_links = $form_state->get('num_links');
        $form_state->set('num_links', $num_links + 1);
        $form_state->setRebuild();
    }

    /**
     * Reverted simple AJAX callback.
     */
    public function addMoreCallback(array &$form, FormStateInterface $form_state) {
        return $form['social_links_wrapper'];
    }
     
    
	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
        $links = $form_state->getValue('social_links_wrapper');

        if (!empty($links) && is_array($links)) {
            foreach ($links as $delta => $link) {
                $title = trim($link['title']);
                $url = trim($link['url']);

                // If one field is filled, the other shouldn't be empty.
                if ($url !== '' && $title === '') {
                    $form_state->setErrorByName(
                        "social_links_wrapper][$delta][title", 
                        $this->t('Link #@num: Please provide a title for this URL.', ['@num' => $delta + 1])
                    );
                }

                // Ensure the URL is valid if provided.
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    $form_state->setErrorByName(
                        "social_links_wrapper][$delta][url", 
                        $this->t('Link #@num: The URL "@url" is not valid.', [
                            '@num' => $delta + 1,
                            '@url' => $url,
                        ])
                    );
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */ 
   /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('indieweb_identity.settings');
        $old_fid = $config->get('avatar')[0] ?? NULL;
        $new_fid = $form_state->getValue('avatar')[0] ?? NULL;
        
        // 1. Handle File Permanence and Usage.
        if ($new_fid && $new_fid != $old_fid) {
            $file = \Drupal\file\Entity\File::load($new_fid);
            if ($file) {
                $file->setPermanent();
                $file->save();
                \Drupal::service('file.usage')->add($file, 'indieweb_identity', 'config', 'hcard_settings');
            }
        }
        
        // 2. Process the Social Links from the wrapper.
        $submitted_links = $form_state->getValue('social_links_wrapper') ?: [];
        $processed_links = [];

        foreach ($submitted_links as $link) {
            // Only save if the URL is not empty.
            if (!empty(trim($link['url']))) {
                $processed_links[] = [
                    'title' => trim($link['title']),
                    'url' => trim($link['url']),
                ];
            }
        }
        
        // 3. Save all configuration.
        $config
            ->set('name', $form_state->getValue('name'))
            ->set('avatar', $form_state->getValue('avatar'))
            ->set('bio', $form_state->getValue('bio'))
            ->set('hidden', $form_state->getValue('hidden'))
            ->set('social_links', $processed_links) // Use our processed array here.
            ->save();
        
        // 4. Feedback and Validation links.
        $host = \Drupal::request()->getSchemeAndHttpHost();
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


