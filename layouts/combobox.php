<?php
/**
 * layouts/combobox.php
 *
 * Layout for combobox field markup. $displayData contains:
 *  - fieldId (int)
 *  - inputName (string)
 *  - value (string)
 *  - uniq (string)
 *
 * This file is intentionally minimal — templates can override this layout to use
 * any other autocomplete tool.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$fieldId   = isset($displayData['fieldId']) ? (int) $displayData['fieldId'] : 0;
$inputName = isset($displayData['inputName']) ? $displayData['inputName'] : '';
$value     = isset($displayData['value']) ? $displayData['value'] : '';
$uniq      = isset($displayData['uniq']) ? $displayData['uniq'] : 'cb_' . substr(md5($inputName . '|' . $fieldId), 0, 8);

$inputId = $uniq . '_input';
$listId  = $uniq . '_list';
?>
<div class="combobox-field" id="<?php echo $uniq; ?>" data-field-id="<?php echo (int) $fieldId; ?>">
    <input id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>"
           class="combobox-input"
           type="text"
           name="<?php echo $inputName; ?>"
           value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"
           autocomplete="off"
           role="combobox"
           aria-autocomplete="list"
           aria-expanded="false"
           aria-controls="<?php echo htmlspecialchars($listId, ENT_QUOTES, 'UTF-8'); ?>"
           data-field-id="<?php echo (int) $fieldId; ?>" />

    <button type="button" class="combobox-toggle" aria-label="<?php echo htmlspecialchars(Text::_('PLG_FIELDS_COMBOBOX_TOGGLE_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
        <span class="combobox-toggle-icon" aria-hidden="true">▾</span>
    </button>

    <div id="<?php echo htmlspecialchars($listId, ENT_QUOTES, 'UTF-8'); ?>" class="combobox-suggestions" hidden aria-hidden="true"></div>
</div>