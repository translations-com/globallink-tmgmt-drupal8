<?php

/**
 * @file
 * Contains Drupal\globallink\GlobalLinkTranslatorUi.
 */

namespace Drupal\globallink;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\globallink\Plugin\tmgmt\Translator\GlobalLinkTranslator;
use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\JobInterface;

/**
 * GlobalLink translator UI.
 */
class GlobalLinkTranslatorUi extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function reviewForm(array $form, FormStateInterface $form_state, JobItemInterface $item) {
    /** @var \Drupal\globallink\Plugin\tmgmt\Translator\GlobalLinkTranslator $translator_plugin */
    $translator_plugin = $item->getTranslator()->getPlugin();
    $translator_plugin->setTranslator($item->getTranslator());
    $mappings = $item->getRemoteMappings();
    /** @var \Drupal\tmgmt\Entity\RemoteMapping $mapping */
    $mapping = array_shift($mappings);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();

    $form['pd_url'] = [
      '#type' => 'url',
      '#title' => t('GlobalLink api url'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('pd_url'),
      '#description' => t('Add the api url provided by translations.com'),
    ];
    $form['pd_username'] = [
      '#type' => 'textfield',
      '#title' => t('GlobalLink username'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('pd_username'),
      '#description' => t('Add the username provided by translations.com'),
    ];
    $form['pd_password'] = [
      '#type' => 'textfield',
      '#title' => t('GlobalLink password'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('pd_password'),
      '#description' => t('Add the password provided by translations.com'),
    ];
    $form['pd_projectid'] = [
      '#type' => 'textfield',
      '#title' => t('GlobalLink project id'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('pd_projectid'),
      '#description' => t('Add the project id provided by translations.com'),
    ];
    $form['pd_submissionprefix'] = [
      '#type' => 'textfield',
      '#title' => t('GlobalLink submission preffix'),
      '#required' => TRUE,
      '#default_value' => $translator->getSetting('pd_submissionprefix'),
      '#description' => t('Choose a prefix'),
    ];
    $form['pd_notify_emails'] = [
      '#type' => 'textfield',
      '#title' => t('Emails for notification'),
      '#default_value' => $translator->getSetting('pd_notify_emails'),
      '#description' => t('A space separated list of emails to notify. Leave blank for no notifications'),
    ];
    $form['pd_notify_level'] = [
      '#type' => 'checkboxes',
      '#title' => t('Email notification levels'),
      '#options' => [
        GlobalLinkTranslator::MSG_STATUS => t('Status'),
        GlobalLinkTranslator::MSG_DEBUG => t('Debug'),
        GlobalLinkTranslator::MSG_WARNING => t('Warning'),
        GlobalLinkTranslator::MSG_ERROR => t('Error'),
      ],
      '#default_value' => $translator->getSetting('pd_notify_level'),
      '#description' => t('Select which tmgmt message types to send via email. Selecting all can result in a high volume of emails being sent.')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $settings = $form['plugin_wrapper']['settings'];
    $adapter = \Drupal::getContainer()->get('globallink.gl_exchange_adapter');
    $values = $form_state->getValue('settings');
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\tmgmt_oht\Plugin\tmgmt\Translator\OhtTranslator $translator_plugin */
    $translator_plugin = $translator->getPlugin();

    try {
      $pd_config = $adapter->getPDConfig([
        'pd_url' => $values['pd_url'],
        'pd_username' => $values['pd_username'],
        'pd_password' => $values['pd_password'],
      ]);

      // Test connections settings.
      $adapter->getGlExchange($pd_config);

      // Test language mappings.
      $all_supported = [];
      // Flatten the array of supported pairs.
      $supported_pairs = $translator_plugin->getSupportedLanguagePairs($translator);
      foreach ($supported_pairs as $supported_pair) {
        foreach ($supported_pair as $item) {
          $all_supported[$item] = $item;
        }
      }

      $unsupported = [];
      $mappings = $form_state->getValue('remote_languages_mappings');
      foreach ($mappings as $mapping) {
        if (!in_array($mapping, $all_supported)) {
          $unsupported[] = $mapping;
        }
      }

      if ($unsupported) {
        $form_state->setError($settings = $form['plugin_wrapper']['remote_languages_mappings'], t('The following language codes are not supported by this project: %codes', ['%codes' => implode(', ', $unsupported)]));
      }

      // Validate email addresses.
      if (!empty($values['pd_notify_emails'])) {
        $emails = explode(' ', $values['pd_notify_emails']);
        $email_validator = \Drupal::service('email.validator');
        $invalid_emails = [];
        foreach ($emails as $email) {
          trim($email);
          if (!$email_validator->isValid($email)) {
            $invalid_emails[] = $email;
          }
        }

        if ($invalid_emails) {
          $form_state->setError($settings['pd_notify_emails'], t('Invalid email address(es) found: %emails', ['%emails' => implode(' ', $invalid_emails)]));
        }
      }
    }
    catch (\Exception $e) {
      $form_state->setError($settings, t('Login credentials are incorrect.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutSettingsForm(array $form, FormStateInterface $form_state, JobInterface $job) {
    /** @var \Drupal\globallink\Plugin\tmgmt\Translator\GlobalLinkTranslator $translator_plugin */
    $translator_plugin = $job->getTranslator()->getPlugin();
    $translator_plugin->setTranslator($job->getTranslator());

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => t('Instructions'),
      '#description' => t('You can provide a set of instructions so that the translator will better understand your requirements.'),
      '#default_value' => $job->getSetting('comment') ? $job->getSetting('comment') : '',
    ];

    $form['due'] = [
      '#type' => 'datetime',
      '#title' => t('Due date and time'),
      '#required' => TRUE,
      '#default_value' => $job->getSetting('due') ? $job->getSetting('due') : '',
      '#element_validate' => [[get_class($this), 'validateDueDate']],
      '#date_increment' => 60,
      '#description' => t('Due will be set using %timezone timezone.', ['%timezone' => drupal_get_user_timezone()]),
    ];

    $form['urgent'] = [
      '#type' => 'checkbox',
      '#title' => t('Urgent'),
      '#default_value' => $job->getSetting('urgent') ? $job->getSetting('urgent') : FALSE,
      '#description' => t('Translation will be treated as high priority and may result in additional fees.'),
    ];

    return $form;
  }

  /**
   * Validate that the due date is in the future.
   *
   * @param array $element
   *   The input element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateDueDate(array $element, FormStateInterface &$form_state) {
    $current_date = new DrupalDateTime();
    if (isset($element['#value']['object'])) {
      $due_date = $element['#value']['object'];

      if ($due_date <= $current_date) {
        $form_state->setError($element, t('Due date must be in the future.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(JobInterface $job) {
    $form = [];

    if ($job->isActive()) {
      $form['actions']['pull'] = [
        '#type' => 'submit',
        '#value' => t('Pull translations'),
        '#submit' => [[$this, 'submitPullTranslations']],
        '#weight' => -10,
      ];
    }

    return $form;
  }

  /**
   * Submit callback to pull translations form GlobalLink.
   */
  public function submitPullTranslations(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\tmgmt\Entity\Job $job */
    $job = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\globallink\Plugin\tmgmt\Translator\GlobalLinkTranslator $translator_plugin */
    $translator_plugin = $job->getTranslator()->getPlugin();
    $translator_plugin->fetchJobs($job);
    tmgmt_write_request_messages($job);
  }
}
