<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.ComboBox
 *
 * @copyright   Copyright (C) NPEU 2026.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\Fields\ComboBox\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Event\User;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Loader\Loader as JLoader;
use Joomla\CMS\Log\Log;

use NPEU\Plugin\Fields\ComboBox\Helper\FieldRegistry;


Log::addLogger(
    array('text_file' => 'debug-combobox.php'),
    Log::ALL,
    array('plg_fields_combobox') // change to your component/plugin name
);

/**
 * ComboBox custom field with autocomplete and persistent options.
 */
class ComboBox extends FieldsPlugin implements SubscriberInterface
{
    #use UserFactoryAwareTrait;

    protected $autoloadLanguage = true;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Field type name
     *
     * @var string
     */
    protected $type = 'combobox';

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;
        #Log::add('__construct: ', \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
        #Log::add('Combobox plugin constructor: field path -> ' . dirname(__DIR__) . '/Field', \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);
        #Log::add('__construct: ' . dirname(__DIR__) . '/Field', \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        $nsClass = \NPEU\Plugin\Fields\ComboBox\Field\Combobox::class;
        $legacy  = 'JFormFieldCombobox';

        if (! class_exists($legacy, false)) {
            // Try Joomla loader alias first (preferred)
            try {
                if (class_exists('JLoader')) {
                    // registerAlias expects original class name (fully-qualified) as second arg
                    \JLoader::registerAlias($legacy, $nsClass);
                }

                // If aliasing via JLoader didn't actually make the class available,
                // create a runtime alias as a fallback.
                if (! class_exists($legacy, false) && class_exists($nsClass, false)) {
                    class_alias($nsClass, $legacy);
                }
            } catch (\Throwable $e) {
                // As a last resort ensure the alias exists
                if (! class_exists($legacy, false) && class_exists($nsClass, false)) {
                    class_alias($nsClass, $legacy);
                }
            }
        }
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? array_merge(parent::getSubscribedEvents(), [
            'onAjaxCombobox'       => 'onAjaxCombobox',
            'onUserAfterSave'      => 'onUserAfterSave',
            #'onContentPrepareForm' => 'onContentPrepareForm',
            #'onCustomFieldsPrepareField' => 'onCustomFieldsPrepareField'
        ]) : [];
    }

    /**
     * Determine whether we are rendering a display-only context
     * (e.g. profile view page) rather than an edit/registration form.
     *
     * @param   string  $context
     * @return  bool
     */
    protected function isDisplayContext(string $context): bool
    {
        $app   = \Joomla\CMS\Factory::getApplication();
        $input = $app->input;

        $option = $input->getCmd('option');
        $view   = $input->getCmd('view');
        $layout = $input->getCmd('layout');
        $task   = $input->getCmd('task');

        // 1️ If Joomla context explicitly indicates a form, it's NOT display
        if (strpos($context, '.form') !== false) {
            return false;
        }

        // 2️ Standard com_users edit/registration forms
        if (
            $option === 'com_users' &&
            (
                $view === 'registration'
                || $layout === 'edit'
                || in_array($task, ['edit', 'save', 'apply', 'register'], true)
            )
        ) {
            return false;
        }

        // 3️ If we are in administrator editing user
        if (
            $app->isClient('administrator') &&
            $option === 'com_users' &&
            $view === 'user' &&
            $layout === 'edit'
        ) {
            return false;
        }

        // Everything else is considered display-only
        return true;
    }

    /**
     * Render the field input for the frontend / backend form
     *
     * @param   string     $context  The context
     * @param   object     $item     The item
     * @param   \stdClass  $field    The field
     *
     * @return  ?string
     *
     */
    public function onCustomFieldsPrepareField($context, $item, $field)
    {
        #Log::add('onCustomFieldsPrepareField: ' . $context, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');

        // Only act for our field type
        if (strtolower($field->type) !== strtolower($this->type))
        {
            return null;
        }

        // Instead of generating input markup here, only prepare a display value
        // Let the FormField handle rendering of inputs for forms.
        $value = isset($field->value) ? (string) $field->value : '';

        // context and name as available to the plugin
        $fieldContext = (string) $context; // the context passed to the event
        $fieldName    = isset($field->name) ? (string) $field->name : '';

        // set registry
        if ($fieldName !== '' && !empty($field->id)) {
            FieldRegistry::set($fieldContext, $fieldName, (int) $field->id);
        }

        // If the context expects display-only HTML, return the escaped value
        // Otherwise return null and allow Joomla form rendering to call the FormField
        if ($this->isDisplayContext($context)) {
            return '<div class="combobox-display-value">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        // For all other cases, return null so Joomla will use the FormField when rendering forms
        return null;

    }

    /**
     * AJAX endpoint used by com_ajax
     * URL: index.php?option=com_ajax&plugin=combobox&group=fields&format=json&field_id=...&q=...
     */
    public function onAjaxCombobox(AjaxEvent $event)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        // action param (use 'action' so it's clear it's our router)
        $action = $input->getCmd('action', 'adminList');

        // allow caller to request JSON explicitly
        $format = $input->getCmd('format', '');

        // Basic ACL: restrict admin actions to authorised users
        $user = Factory::getUser();

        // route to internal handlers
        switch ($action) {
            case 'adminList':
                // admin list should be limited to authorized users (administrator)
                if (!$user->authorise('core.manage')) {
                    return $this->ajaxResponse(['error' => 'Unauthorized'], 403, $format);
                }
                return $this->handleAdminList($format);

            case 'adminDelete':
                if (!$user->authorise('core.manage')) {
                    return $this->ajaxResponse(['error' => 'Unauthorized'], 403, $format);
                }

                // CSRF protection: ensure POST and valid token for state change
                if ($app->input->getMethod() !== 'POST' || !Session::checkToken()) {
                    return $this->ajaxResponse(['error' => 'Invalid token'], 403, $format);
                }
                return $this->handleAdminDelete($format);

            case 'options':
                // public options endpoint (returns JSON list of options for a field)
                return $this->handleOptionsList($format);

            default:
                return $this->ajaxResponse(['error' => 'Unknown action'], 400, $format);
        }
    }

    /**
     * Standard JSON/HTML responder
     */
    protected function ajaxResponse($payload, int $httpCode = 200, string $format = '')
    {
        $app = Factory::getApplication();

        if ($format === 'json' || $app->input->getCmd('format') === 'json') {
            $app->setHeader('Content-Type', 'application/json', true);
            http_response_code($httpCode);
            echo json_encode($payload, JSON_UNESCAPED_SLASHES);
            $app->close();
        }

        // fallback: if payload is HTML string, output it (compat)
        if (is_string($payload)) {
            echo $payload;
            $app->close();
        }

        // otherwise return JSON by default
        $app->setHeader('Content-Type', 'application/json', true);
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        $app->close();
    }

    protected function handleAdminList(string $format)
    {
        $input   = Factory::getApplication()->input;
        $fieldId = (int) $input->getInt('field_id', 0);

        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select($db->quoteName(['id', 'value', 'created']))
            ->from($db->quoteName('#__field_combo_options'))
            ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
            ->order($db->quoteName('created') . ' DESC');

        $db->setQuery($q);
        $rows = $db->loadAssocList();

        // If JSON requested, return as JSON structure
        if ($format === 'json') {
            return $this->ajaxResponse(['results' => $rows], 200, 'json');
        }

        // Fallback HTML: simple table (used only if somebody calls without format=json)
        $html = '<table class="com-table combobox-admin-table"><thead><tr><th>ID</th><th>Value</th><th>Created</th><th>Action</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>' . (int) $r['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($r['value'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['created'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td><button class="btn btn-danger btn-sm combobox-admin-delete" data-id="' . (int) $r['id'] . '">Delete</button></td></tr>';
        }
        $html .= '</tbody></table>';

        return $this->ajaxResponse($html, 200, '');
    }

    protected function handleAdminDelete(string $format)
    {
        $input = Factory::getApplication()->input;
        $id    = (int) $input->getInt('id', 0);

        if ($id <= 0) {
            return $this->ajaxResponse(['error' => 'Invalid id'], 400, $format);
        }

        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->delete($db->quoteName('#__field_combo_options'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($q);

        try {
            $db->execute();
            return $this->ajaxResponse(['success' => true], 200, $format);
        } catch (\Exception $e) {
            return $this->ajaxResponse(['error' => $e->getMessage()], 500, $format);
        }
    }

    protected function handleOptionsList(string $format)
    {
        // Public endpoint used by front-end combobox (prefetch)
        $input   = Factory::getApplication()->input;
        $fieldId = (int) $input->getInt('field_id', 0);
        $limit   = (int) $input->getInt('limit', 200);

        $db = Factory::getDbo();
        $q  = $db->getQuery(true)
            ->select($db->quoteName(['id', 'value']))
            ->from($db->quoteName('#__field_combo_options'))
            ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
            ->order($db->quoteName('value') . ' ASC')
            ->setLimit($limit);

        $db->setQuery($q);
        $rows = $db->loadAssocList();
        return $this->ajaxResponse(['results' => $rows], 200, 'json');
    }


    /**
     * Sanitize and validate a candidate option value.
     *
     * Allowed characters:
     *  - Unicode letters (\p{L})
     *  - Unicode numbers (\p{N})
     *  - Spaces and selected punctuation: - . ' ’ , : ( ) &
     *
     * @param  string  $value
     * @param  int     $maxLength
     * @return string|false
     */
    protected function sanitizeAndValidateValue(string $value, int $maxLength = 255)
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        // Normalize multiple spaces
        $value = preg_replace('/\s+/u', ' ', $value);

        // Allowed pattern (Unicode letters/numbers and common punctuation in names/titles)
        $allowedPattern = '/^[\p{L}\p{N}\s\-\.\'\x{2019}\,\:\(\)\&]+$/u';

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            return false;
        }

        if (!preg_match($allowedPattern, $value)) {
            return false;
        }

        // Trim leading/trailing punctuation/spaces
        $value = trim($value, " \t\n\r\0\x0B-.,:()&'’");

        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            return false;
        }

        return $value;
    }

    /**
     * Helper: insert/update an option in the shared table
     *
     * @param int $fieldId
     * @param string $value
     * @param int|null $userId
     * @return void
     */
    protected function saveOptionIfNew($fieldId, $value, $userId = null)
    {
        $value = (string) $value;

        // Sanitize & validate
        $clean = $this->sanitizeAndValidateValue($value, 255);
        if ($clean === false) {
            return;
        }

        $db = Factory::getDbo();
        $tableName = $db->getPrefix() . 'field_combo_options';

        // normalization choice: keep case as-is, but could mb_strtolower if desired
        $normValue = $clean;

        $sql = "
        INSERT INTO `{$tableName}` (`field_id`, `value`, `created`)
        VALUES (" . (int)$fieldId . ", " . $db->quote($normValue) . ", NOW());
        ";
        #Log::add('INSERT query: ' . (string) $sql, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
        try {
            $db->setQuery($sql);
            $db->execute();
        } catch (\Exception $e) {
            // suppressed; optionally log
        }
    }

    /**
     * Hook: called after content (including users) is saved.
     *
     * Detects com_users.user saves, finds combobox fields attached to users,
     * reads their saved values from #__fields_values for this user, and inserts new options.
     */
    /*public function onUserAfterSave(Event $event): void
    {
        [$context, $table, $isNew] = array_values($event->getArguments());

        Log::add('context: ' . print_r($context, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');*/
    public function onUserAfterSave(User\AfterSaveEvent $event): void
    {
        if (!$event->getSavingResult()) {
            return;
        }

        $user = $event->getUser();

        #Log::add('user: ' . print_r($user, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');


        $userId = (int) $user['id'];

        #Log::add('userId: ' . print_r($userId, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
        if ($userId <= 0) {
            return;
        }

        $db = Factory::getDbo();



        // Find all combobox fields targeting com_users.user context and state
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'name', 'state']))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($this->type))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_users.user'));

        $db->setQuery($query);
        #Log::add('query: ' . (string) $query, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
        try {
            $fields = $db->loadObjectList();
        } catch (\Exception $e) {
            $fields = [];
        }


        #Log::add('fields: ' . print_r($fields, true), \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');


        if (empty($fields)) {
            return;
        }

        foreach ($fields as $f) {
            // Optionally skip unpublised fields
            if (isset($f->state) && (int)$f->state === 0) {
                continue;
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('value'))
                ->from($db->quoteName('#__fields_values'))
                ->where($db->quoteName('field_id') . ' = ' . (int)$f->id)
                ->where($db->quoteName('item_id') . ' = ' . (int)$userId);

            $db->setQuery($query);
            #Log::add('query for  ' . $f->id .': ' . (string) $query, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
            try {
                $val = $db->loadResult();
            } catch (\Exception $e) {
                $val = null;
            }
            #Log::add('val for  ' . $f->id .': ' . $val, \Joomla\CMS\Log\Log::INFO, 'plg_fields_combobox');
            if ($val !== null && $val !== '') {
                $this->saveOptionIfNew($f->id, $val, $userId);
            }
        }
    }
}