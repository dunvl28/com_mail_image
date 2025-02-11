<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Member Table class.
 *
 * @since  1.0
 */
class MemberTable extends Table implements VersionableTableInterface, TaggableTableInterface
{
    use TaggableTableTrait;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;

    /**
     * Ensure the params and metadata in json encoded in the bind method
     *
     * @var    array
     * @since  3.3
     */
    protected $_jsonEncode = ['params', 'metadata'];

    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   1.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_mail_image.ip';

        parent::__construct('#__mail_members', 'id', $db);

        $this->setColumnAlias('title', 'name');
    }

    /**
     * Stores an ip.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   1.6
     */
    public function store($updateNulls = true)
    {
        $date   = Factory::getDate()->toSql();
        $userId = Factory::getUser()->id;

        // Set created date if not set.
        if (!(int) $this->created) {
            $this->created = $date;
        }

        if ($this->id) {
            // Existing item
            $this->modified_by = $userId;
            $this->modified    = $date;
        } else {
            // Field created_by field can be set by the user, so we don't touch it if it's set.
            if (empty($this->created_by)) {
                $this->created_by = $userId;
            }

            if (!(int) $this->modified) {
                $this->modified = $date;
            }

            if (empty($this->modified_by)) {
                $this->modified_by = $userId;
            }
        }

        // Store utf8 email as punycode
        if ($this->email !== null) {
            $this->email = PunycodeHelper::emailToPunycode($this->email);
        }

        // Verify that the alias is unique
        $table = Table::getInstance('IpTable', __NAMESPACE__ . '\\', ['dbo' => $this->getDbo()]);

        if ($table->load(['alias' => $this->alias]) && ($table->id != $this->id || $this->id == 0)) {
            // Is the existing Ip trashed?
            $this->setError(Text::_('COM_MAIL_IMAGE_ERROR_UNIQUE_ALIAS'));

            if ($table->published === -2) {
                $this->setError(Text::_('COM_MAIL_IMAGE_ERROR_UNIQUE_ALIAS_TRASHED'));
            }

            return false;
        }

        return parent::store($updateNulls);
    }

    /**
     * Overloaded check function
     *
     * @return  boolean  True on success, false on failure
     *
     * @see     \JTable::check
     * @since   1.5
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->default_con = (int) $this->default_con;

        // Check for valid name
        if (trim($this->name) == '') {
            $this->setError(Text::_('COM_MAIL_IMAGE_WARNING_PROVIDE_VALID_IP'));

            return false;
        }

        // Generate a valid alias
        $this->generateAlias();

        // Check the publish down date is not earlier than publish up.
        if ((int) $this->publish_down > 0 && $this->publish_down < $this->publish_up) {
            $this->setError(Text::_('JGLOBAL_START_PUBLISH_AFTER_FINISH'));

            return false;
        }

        if (!$this->id) {
            // Hits must be zero on a new item
            $this->hits = 0;
        }

        // Set publish_up, publish_down to null if not set
        if (!$this->publish_up) {
            $this->publish_up = null;
        }

        if (!$this->publish_down) {
            $this->publish_down = null;
        }

        if (!$this->modified) {
            $this->modified = $this->created;
        }

        if (empty($this->modified_by)) {
            $this->modified_by = $this->created_by;
        }

        return true;
    }

    /**
     * Generate a valid alias from title / date.
     * Remains public to be able to check for duplicated alias before saving
     *
     * @return  string
     */
    public function generateAlias()
    {
        if (empty($this->alias)) {
            $this->alias = $this->name;
        }

        $this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language);

        if (trim(str_replace('-', '', $this->alias)) == '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        return $this->alias;
    }


    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   4.0.0
     */
    public function getTypeAlias()
    {
        return $this->typeAlias;
    }
}
