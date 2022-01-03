<?php

require_once 'agbucontributiontab.civix.php';
use CRM_Agbucontributiontab_ExtensionUtil as E;

/**
 * Add a whole mess of extra fields to the contribution tab.
 */
function agbucontributiontab_civicrm_searchColumns($objectName, &$headers, &$values, &$selector) {
  if ($objectName == 'contribution' && !empty($values)) {
    // Remove the action links, we'll re-add them later.
    unset($headers[6]);
    $fieldArray = [
      [
        'name' => 'custom_40',
        'label' => E::ts('Appeal'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'custom_109',
        'label' => E::ts('Acknowledgee'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'custom_111',
        'label' => E::ts('Is tribute acknowledged?'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'custom_96',
        'label' => E::ts('Ack. Type'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'custom_107',
        'label' => E::ts('Ack. By'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'custom_5',
        'label' => E::ts('Matching Gift'),
        'callback' => 'displayCustom',
      ],
      [
        'name' => 'matching_gift_status',
        'label' => E::ts('Matching Gift Status'),
        'callback' => 'getContributionStatus',
      ],
      [
        'name' => 'note',
        'label' => E::ts('Note'),
        'callback' => 'ellipsify',
      ],
      [
        'name' => 'soft_credit_name',
        'label' => E::ts('IHO/IMO'),
        'callback' => 'getDisplayName',
      ],
      [
        'name' => 'soft_credit_type',
        'label' => E::ts('Tribute Type'),
        'callback' => 'getSoftCreditType',
      ],
    ];
    $contributionIds = CRM_Utils_Array::collect('contribution_id', $values);
    // Add the additional contribution fields to the values array.
    $contributionFields = civicrm_api3('Contribution', 'get', [
      'id' => ['IN' => $contributionIds],
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($values as $k => $value) {
      if (isset($contributionFields[$value['contribution_id']])) {
        $values[$k] = array_merge($contributionFields[$value['contribution_id']], $value);
      }
    }

    // Add the note to the values array.
    $notesRaw = civicrm_api3('Note', 'get', [
      'return' => ["note", "entity_id"],
      'entity_table' => "civicrm_contribution",
      'entity_id' => ['IN' => $contributionIds],
      'options' => ['limit' => 0],
    ])['values'];
    // Convert $notesRaw into a more useful format, then add them to the values array..
    foreach ($notesRaw as $note) {
      $notes[$note['entity_id']] = ['note' => $note['note']];
    }
    foreach ($values as $k => $value) {
      if (isset($notes[$value['contribution_id']])) {
        $values[$k] = array_merge($value, $notes[$value['contribution_id']]);
      }
    }

    // Convert $matchingGiftsRaw into a more useful format, then add them to the values array.
    $matchingGiftsRaw = civicrm_api3('ContributionSoft', 'get', [
      'return' => ["contribution_id.contribution_status_id", "custom_4"],
      'custom_4' => ['IN' => $contributionIds],
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($matchingGiftsRaw as $matchingGift) {
      $matchingGifts[$matchingGift['custom_4']] = ['matching_gift_status' => $matchingGift['contribution_id.contribution_status_id']];
    }
    foreach ($values as $k => $value) {
      if (isset($matchingGifts[$value['contribution_id']])) {
        $values[$k] = array_merge($value, $matchingGifts[$value['contribution_id']]);
      }
    }


    // Grab IHO/IMO data for the contributions.
    $softCreditsRaw = civicrm_api3('ContributionSoft', 'get', [
      'soft_credit_type_id' => ['IN' => ["in_honor_of", "in_memory_of"]],
      'contribution_id' => ['IN' => $contributionIds],
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($softCreditsRaw as $softCredit) {
      $softCredits[$softCredit['contribution_id']] = [
        'soft_credit_name' => $softCredit['contact_id'],
        'soft_credit_type' => $softCredit['soft_credit_type_id'],
      ];
    }
    foreach ($values as $k => $value) {
      if (isset($softCredits[$value['contribution_id']])) {
        $values[$k] = array_merge($value, $softCredits[$value['contribution_id']]);
      }
    }
    formatValues($values, $fieldArray);

    // Add the data.
    $headerId = 6;
    foreach ($fieldArray as $field) {
      $headers[$headerId]['field_name'] = $field['name'];
      $headers[$headerId]['name'] = $field['label'];
      $headers[$headerId]['weight'] = $headerId * 10;
      $headerId++;
    }

    // Re-add the action links.
    $headers[] = ['desc' => 'Actions', 'type' => 'actions', 'weight' => 99999];
  }
}

/**
 * We format the values - pseudoconstants are resolved, money fields are formatted as money, etc.
 * @param type $values
 * @param type $fieldArray
 */
function formatValues(&$values, $fieldArray) {
  foreach ($fieldArray as $field) {
    if (isset($field['callback'])) {
      foreach ($values as $k => $value) {
        if (isset($value[$field['name']])) {
          $values[$k][$field['name']] = call_user_func($field['callback'], $value[$field['name']], $field['name']);
        }
      }
    }
  }
}

function displayCustom($data, $field) {
  return CRM_Core_BAO_CustomField::displayValue($data, $field);
}

function ellipsify($data, $field) {
  return CRM_Utils_String::ellipsify($data, 80);
}

function getContributionStatus($data, $field) {
  if ($data) {
    return CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id', 'validate')[$data];
  }
  return NULL;
}

function getSoftCreditType($data, $field) {
  if ($data) {
    return civicrm_api3('ContributionSoft', 'getoptions', ['field' => "soft_credit_type_id"])['values'][$data];
  }
  return NULL;
}

function getDisplayName($data, $field) {
  if ($data) {
    return civicrm_api3('Contact', 'getvalue', ['return' => "display_name", 'id' => $data]);
  }
  return NULL;
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function agbucontributiontab_civicrm_config(&$config) {
  _agbucontributiontab_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function agbucontributiontab_civicrm_xmlMenu(&$files) {
  _agbucontributiontab_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function agbucontributiontab_civicrm_install() {
  _agbucontributiontab_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function agbucontributiontab_civicrm_postInstall() {
  _agbucontributiontab_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function agbucontributiontab_civicrm_uninstall() {
  _agbucontributiontab_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function agbucontributiontab_civicrm_enable() {
  _agbucontributiontab_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function agbucontributiontab_civicrm_disable() {
  _agbucontributiontab_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function agbucontributiontab_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _agbucontributiontab_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function agbucontributiontab_civicrm_managed(&$entities) {
  _agbucontributiontab_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function agbucontributiontab_civicrm_caseTypes(&$caseTypes) {
  _agbucontributiontab_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function agbucontributiontab_civicrm_angularModules(&$angularModules) {
  _agbucontributiontab_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function agbucontributiontab_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _agbucontributiontab_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function agbucontributiontab_civicrm_entityTypes(&$entityTypes) {
  _agbucontributiontab_civix_civicrm_entityTypes($entityTypes);
}
