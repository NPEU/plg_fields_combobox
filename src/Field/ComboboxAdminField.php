<?php
declare(strict_types=1);

namespace NPEU\Plugin\Fields\ComboBox\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;


use Joomla\CMS\Log\Log;


Log::addLogger(
    array('text_file' => 'debug-combobox.php'),
    Log::ALL,
    array('plg_fields_combobox') // change to your component/plugin name
);


\defined('_JEXEC') or die;

final class ComboboxAdminField extends FormField
{
    protected $type = 'ComboboxAdmin';

    /**
     * Get the layouts paths
     *
     * @return  array
     *
     * @since   3.5
     */
    protected function getLayoutPaths()
    {
        $template = Factory::getApplication()->getTemplate();

        $r = [
            JPATH_ADMINISTRATOR . '/templates/' . $template . '/html/layouts/plugins/fields/combobox',
            JPATH_PLUGINS . '/fields/combobox/layouts',
            JPATH_SITE . '/layouts',
        ];
        #Log::add('paths: ' . print_r($r, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        return $r;
    }

    protected function attachAdminAssets(): void
    {
        $doc = Factory::getDocument();
        $wa  = $doc->getWebAssetManager();

        $base = Uri::root(true) . '/plugins/fields/combobox/assets';
        #Log::add('base: ' . $base, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        // register & use assets
        #$wa->registerAndUseScript('plg_combobox.adminjs', $base . '/combobox-admin.js');
        #$wa->registerAndUseStyle('plg_combobox.admincss', $base . '/combobox-admin.css');
        $doc->addStyleSheet($base . '/combobox-admin.css');
        $doc->addScript($base . '/combobox-admin.js');

        // Expose token name and translated strings to JS
        $tokenName = \Joomla\CMS\Session\Session::getFormToken();

        $strings = [
            'confirmOk'     => Text::_('PLG_FIELDS_COMBOBOX_CONFIRM_OK'),
            'confirmCancel' => Text::_('PLG_FIELDS_COMBOBOX_CONFIRM_CANCEL'),
            'confirmTitle'  => Text::_('PLG_FIELDS_COMBOBOX_CONFIRM_TITLE'), // optional
        ];

        $data = [
            'tokenName' => $tokenName,
            'strings'   => $strings,
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $doc->addScriptDeclaration('window.plgComboBoxAdmin = ' . $json . ';');
    }

    protected function getInput(): string
    {
        // Attach assets
        $this->attachAdminAssets();

        $uniq = 'combobox-admin-' . md5($this->name);
        $containerId = $uniq . '-panel';
        $tokenName = Session::getFormToken();

        $html = [];

        $html   = [];
        $html[] = '<div id="' . $containerId . '" class="combobox-admin-wrap"';
        $html[] = ' data-token-name="' . htmlspecialchars($tokenName, ENT_QUOTES) . '">';
        $html[] = '  <h3>' . \Joomla\CMS\Language\Text::_('PLG_FIELDS_COMBOBOX_ADMIN_MANAGER') . '</h3>';
        $html[] = '  <div class="combobox-admin-message" data-placeholder>' . \Joomla\CMS\Language\Text::_('PLG_FIELDS_COMBOBOX_ADMIN_NO_FIELD_SELECTED') . '</div>';
        $html[] = '  <div class="combobox-admin-panel" data-panel></div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Method to get the field label markup for a spacer.
     * Use the label text or name from the XML element as the spacer or
     * Use a hr="true" to automatically generate plain hr markup
     *
     * @return  string  The field label markup.
     */
    protected function getLabel()
    {
        return '';
    }
}
