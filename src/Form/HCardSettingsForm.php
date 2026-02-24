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
	
		$form['social_links'] = [
			'#type' => 'textarea',
			'#title' => $this->t('Social Links (YAML)'),
			'#default_value' => $config->get('social_links'),
			'#description' => $this->t('Social links will receive the u-url class and re="me"'),
		];
		
		$form['hidden'] = [
			'#type'  => 'checkbox',
			'#title' => $this->t('Hidden'),
			'#description' => $this->t('Should the h-card be visually hidden?'),
			'#default_value' => $config->get('hidden')      
			];

        return parent::buildForm($form, $form_state);
    }


	/**
	 * {@inheritdoc}
	 */
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$social_links = $form_state->getValue('social_links');
		
		if (!empty($social_links)) {
			try {
			  $parsed = Yaml::parse($social_links);
			  if (!is_array($parsed)) {
				$form_state->setErrorByName('social_links', $this->t('Social links must be a valid YAML list.'));
			  }
			} catch (ParseException $exception) {
			  $form_state->setErrorByName('social_links', $this->t('YAML Parse Error: @error', ['@error' => $exception->getMessage()]));
			}
		}
	}

    /**
     * {@inheritdoc}
     */ 
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('indieweb_identity.settings');
        $old_fid = $config->get('avatar')[0] ?? NULL;
        $new_fid = $form_state->getValue('avatar')[0] ?? NULL;
        
        // 1. Handle File Permanence and Usage
        if ($new_fid && $new_fid != $old_fid) {
            $file = \Drupal\file\Entity\File::load($new_fid);
            $file->setPermanent();
            $file->save();
            // Add usage so Drupal knows not to delete it.
            \Drupal::service('file.usage')->add($file, 'indieweb_identity', 'config', 'hcard_settings');
        }
        
        $config
            ->set('name', $form_state->getValue('name'))
            ->set('avatar', $form_state->getValue('avatar'))
            ->set('bio', $form_state->getValue('bio'))
            ->set('hidden', $form_state->getValue('hidden'))
            ->set('social_links', $form_state->getValue('social_links'))
            ->save();
        
        $host = \Drupal::request()->getSchemeAndHttpHost();
		$validator_url = "https://indiewebify.me/validate-h-card/?url=" . urlencode($host);
		
		// 2. Clear instructions for the user.
		$this->messenger()->addStatus($this->t('<strong>Next Step:</strong> Ensure the "IndieWeb H-Card" block is placed in a region on your <a href=":home">home page</a>.', [
		':home' => $host,
		]));
		
		$this->messenger()->addStatus($this->t('Once the block is visible, you can <a href=":url" target="_blank" rel="noopener">run the IndieWebify Validator</a>.', [
		':url' => $validator_url,
		]));
        parent::submitForm($form, $form_state);
    }
}


