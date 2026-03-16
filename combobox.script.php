<?php
/**
 * @package     Joomla.Site
 * @subpackage  plg_field_combobox
 *
 * @copyright   Copyright (C) NPEU 2026.
 * @license     MIT License; see LICENSE.md
 */


\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class PlgFieldsComboboxInstallerScript
{
    public function postflight($type, $parent)
    {
        $this->createSharedOptionsTable();
    }

    protected function createSharedOptionsTable()
    {
        $db = Factory::getDbo();
        $prefix = $db->getPrefix();
        $table = $prefix . 'field_combo_options';

        $sql = "
        CREATE TABLE IF NOT EXISTS `{$table}` (
          `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          `field_id` INT(10) UNSIGNED NOT NULL,
          `value` VARCHAR(255) NOT NULL,
          `created` DATETIME NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `ux_field_value` (`field_id`,`value`),
          KEY `idx_field` (`field_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        try {
            $db->setQuery($sql);
            $db->execute();
        } catch (\Exception $e) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Combobox plugin: could not create table ' . $table . '. Error: ' . $e->getMessage(), 'warning');
        }
    }

    public function uninstall($parent)
    {
        // Intentionally left blank to avoid accidental data loss.
    }
}
