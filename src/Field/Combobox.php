<?php
declare(strict_types=1);

namespace NPEU\Plugin\Fields\ComboBox\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;

use Joomla\CMS\Log\Log;

use NPEU\Plugin\Fields\ComboBox\Helper\FieldRegistry;

Log::addLogger(
    array('text_file' => 'debug-combobox.php'),
    Log::ALL,
    array('plg_fields_combobox') // change to your component/plugin name
);


/**
 * Combobox FormField — renders the interactive input when Joomla builds forms
 */
class Combobox extends FormField
{
    /**
     * The form field type.
     *
     * @var    string
     */
    protected $type = 'combobox';

    /**
     * Get the input markup for the field (only called during form rendering).
     *
     * @return  string  The field input markup.
     */
    protected function getInput(): string
    {
        #Log::add('getInput: '.  $this->id, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        // build unique ids (so multiple instances on the form don't clash)
        $uniq = 'cb_' . substr(md5($this->name . '|' . $this->id), 0, 8);
        $inputId = $uniq . '_input';
        $listId  = $uniq . '_list';

        // Field id, name and value
        // 1) Try registry first
        $fieldName = '';
        if (isset($this->element['name'])) {
            $fieldName = (string) $this->element['name'];
        } elseif (! empty($this->fieldname)) {
            $fieldName = (string) $this->fieldname;
        }

        // get current context heuristically (use form name or element attr)
        $context = '';
        if (isset($this->element['context'])) {
            $context = (string) $this->element['context'];
        } elseif ($this->form && method_exists($this->form, 'getName')) {
            $context = (string) $this->form->getName(); // e.g. com_users.user
        }

        $fieldId = FieldRegistry::get($context, $fieldName);

        // 2) Fallback: query #__fields by name and permissive context match
        if (empty($fieldId) && $fieldName !== '') {
            $db = \Joomla\CMS\Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($fieldName))
                ->where($db->quoteName('type') . ' = ' . $db->quote('combobox'));

            // For com_users fields we allow any com_users.* context as fallback
            if ($context !== '') {
                if (strpos($context, 'com_users') !== false) {
                    $query->where($db->quoteName('context') . ' LIKE ' . $db->quote('com_users.%'));
                } else {
                    // also attempt exact context if different
                    $query->where($db->quoteName('context') . ' = ' . $db->quote($context));
                }
            }

            $db->setQuery($query, 0, 1);
            $res = $db->loadResult();
            if ($res) {
                $fieldId = (int) $res;
                // cache it in registry for next time
                FieldRegistry::set($context, $fieldName, $fieldId);
            }
        }
        #Log::add('ID: ' . print_r($this->element, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');


        $inputName = htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8');
        $value = isset($this->value) ? (string) $this->value : '';


        // Attach assets
        $this->attachAssets();

        // Prepare data for the layout
        $displayData = [
            'fieldId'   => (int) $fieldId,
            'inputName' => $inputName,
            'value'     => (string) $value,
            // useful extra: unique id base so multiple fields won't clash
            'uniq'      => 'cb_' . substr(md5($inputName . '|' . $fieldId), 0, 8),
        ];

        $pluginRoot   = dirname(__DIR__, 2);
        $layoutsPath  = $pluginRoot . '/layouts';

        try
        {
            // Look for layout file 'combobox' in our plugin/layouts folder
            $layout = new FileLayout('combobox', $layoutsPath);
            $rendered = $layout->render($displayData);
        }
        catch (\Exception $e)
        {
            // Fallback: simple inline markup if layout missing or error
            $uniq    = $displayData['uniq'];
            $inputId = $uniq . '_input';
            $listId  = $uniq . '_list';

            $rendered = [];
            $rendered[] = '<div class="combobox-field" id="' . $uniq . '" data-field-id="' . (int) $fieldId . '">';
            $rendered[] = '    <input id="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '"';
            $rendered[] = '           class="combobox-input"';
            $rendered[] = '           type="text"';
            $rendered[] = '           name="' . $inputName . '"';
            $rendered[] = '           value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            $rendered[] = '           autocomplete="off"';
            $rendered[] = '           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . $listId . '"';
            $rendered[] = '           data-field-id="' . (int) $fieldId . '" />';
            $rendered[] = '    <button type="button" class="combobox-toggle" aria-label="' . htmlspecialchars(Text::_('PLG_FIELDS_COMBOBOX_TOGGLE_LABEL', true), ENT_QUOTES, 'UTF-8') . '"><span class="combobox-toggle-icon" aria-hidden="true">▾</span></button>';
            $rendered[] = '    <div id="' . $listId . '" class="combobox-suggestions" hidden aria-hidden="true"></div>';
            $rendered[] = '</div>';

            $rendered = implode("\n", $rendered);
        }

        // Return the rendered HTML to the fields system
        return $rendered;
    }

    /**
     * Attach vanilla JS and CSS
     */
    /*protected function attachAssets()
    {
        $doc = Factory::getDocument();
        static $attached = false;
        if ($attached) return;
        $attached = true;

        // Assets folder path (relative to site root)
        $base = Uri::root(true) . '/plugins/fields/combobox/assets';
        $doc->addStyleSheet($base . '/combobox.css');
        $doc->addScript($base . '/combobox.js');
    }*/


    protected function attachAssets()
    {
        $doc = Factory::getDocument();

        static $attached = false;
        if ($attached) {
            return;
        }
        $attached = true;

        // Paths
        $assetRelative = '/plugins/fields/combobox/assets';
        $baseUrl  = Uri::root(true) . $assetRelative;         // URL accessible by browser
        $basePath = JPATH_ROOT . $assetRelative;             // filesystem path

        $cssFile = $basePath . '/combobox.css';
        $jsFile  = $basePath . '/combobox.js';
        $cssUrl  = $baseUrl  . '/combobox.css';
        $jsUrl   = $baseUrl  . '/combobox.js';

        // Basic existence checks to help diagnose path problems early
        if (!is_file($cssFile) || !is_file($jsFile)) {
            // Log and fall back to best-effort registration; don't throw
            Log::add(sprintf('Combobox assets not found. Expected: %s and %s', $cssFile, $jsFile), Log::WARNING, 'plg_fields_combobox');
        }

        // Try WebAssetManager (Joomla 4/6). If anything about WAM fails, we'll fall back.
        try
        {
            if (method_exists($doc, 'getWebAssetManager'))
            {
                $wa = $doc->getWebAssetManager();

                // Guard in case the WebAssetManager variant doesn't provide the helper methods
                if (method_exists($wa, 'registerAndUseStyle') && method_exists($wa, 'registerAndUseScript'))
                {
                    // Register using the public URL (WAM accepts external/absolute URLs)
                    $wa->registerAndUseStyle('plg.fields.combobox.styles', $cssUrl);
                    $wa->registerAndUseScript('plg.fields.combobox.script', $jsUrl);

                    return; // success — assets attached via WAM
                }

                // Some environments expose WAM but without the registerAndUse* convenience methods
                // Fallback to generic register + use if available
                if (method_exists($wa, 'register') && method_exists($wa, 'useStyle') && method_exists($wa, 'useScript'))
                {
                    $wa->register('plg.fields.combobox.styles', $cssUrl, [], []);
                    $wa->useStyle('plg.fields.combobox.styles');

                    $wa->register('plg.fields.combobox.script', $jsUrl, [], []);
                    $wa->useScript('plg.fields.combobox.script');

                    return;
                }

                // If WAM exists but doesn't support registration methods we can use, log and fall back.
                Log::add('WebAssetManager present but lacks registerAndUse* or register/use helpers. Falling back to Document->addScript/addStyleSheet.', Log::DEBUG, 'plg_fields_combobox');
            }
        }
        catch (\Throwable $e)
        {
            // Log the exception and fall through to the fallback registration
            Log::add('WebAssetManager registration error for plg_fields_combobox: ' . $e->getMessage(), Log::ERROR, 'plg_fields_combobox');
        }

        // Final fallback: plain old addScript / addStyleSheet (works everywhere)
        $doc->addStyleSheet($cssUrl);
        $doc->addScript($jsUrl);
    }
}
