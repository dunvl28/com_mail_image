<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Site\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Single item model for an ip
 *
 * @package     Joomla.Site
 * @subpackage  com_mail_image
 * @since       1.5
 */
class ImageModel extends FormModel
{
    /**
     * The name of the view for a single item
     *
     * @var    string
     * @since  1.6
     */
    protected $view_item = 'image';

    /**
     * A loaded item
     *
     * @var    \stdClass[]
     * @since  1.6
     */
    protected $_item = [];

    /**
     * Model context string.
     *
     * @var     string
     */
    protected $_context = 'com_mail_image.image';

    /**
     * Method to get the image form.
     * The base form is loaded from XML and then an event is fired
     *
     * @param   array    $data      An optional array of data for the form to interrogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form  A Form object on success, false on failure
     *
     * @since   1.6
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_mail_image.image', 'image', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array    The default data is an empty array.
     *
     * @since   1.6.2
     */
    protected function loadFormData()
    {
        $data = (array) Factory::getApplication()->getUserState('com_mail_image.image.data', []);

        if (empty($data['language']) && Multilanguage::isEnabled()) {
            $data['language'] = Factory::getLanguage()->getTag();
        }

        $this->preprocessData('com_mail_image.image', $data);

        return $data;
    }

    /**
     * Gets an ip
     *
     * @param   integer  $pk  Id for the ip
     *
     * @return  mixed Object or null
     *
     * @since   1.6.0
     */
    public function getItem($pk = null)
    {
        $pk = $pk ?: (int) $this->getState('ip.id');

        if (!isset($this->_item[$pk])) {
            try {
                $db    = $this->getDatabase();
                $query = $db->getQuery(true);

                $query->select($this->getState('item.select', 'a.*'))
                    ->select( 'a.id,a.name,a.email')
                    ->from($db->quoteName('#__mail_ips', 'a'));

                $ip = $this->getState('filter.ip');
                if(!empty($ip))
                {
                    $query->where('a.name = '.$db->quote($ip));
                }
                else
                {
                    $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $pk, ParameterType::INTEGER);
                }

                // Filter by published state.
                $published = $this->getState('filter.published');
                $archived  = $this->getState('filter.archived');

                if (is_numeric($published)) {
                    $queryString = $db->quoteName('a.published') . ' = :published';

                    if ($archived !== null) {
                        $queryString = '(' . $queryString . ' OR ' . $db->quoteName('a.published') . ' = :archived)';
                        $query->bind(':archived', $archived, ParameterType::INTEGER);
                    }
                }

                $db->setQuery($query);
                $data = $db->loadObject();

                if (empty($data)) {
                    throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_IMAGE_NOT_FOUND'), 404);
                }

                // Check for published state if filter set.
                if ((is_numeric($published) || is_numeric($archived)) && (($data->published != $published) && ($data->published != $archived))) {
                    throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_IMAGE_NOT_FOUND'), 404);
                }

                $this->_item[$pk] = $data;
            } catch (\Exception $e) {
                if ($e->getCode() == 404) {
                    // Need to go through the error handler to allow Redirect to work.
                    throw $e;
                } else {
                    $this->setError($e);
                    $this->_item[$pk] = false;
                }
            }
        }

        return $this->_item[$pk];
    }

    /**
     * Increment the hit counter for the ip.
     *
     * @param   integer  $pk  Optional primary key of the ip to increment.
     *
     * @return  boolean  True if successful; false otherwise and internal error set.
     *
     * @since   3.0
     */
    public function hit($pk = 0)
    {
        $input    = Factory::getApplication()->getInput();
        $hitcount = $input->getInt('hitcount', 1);

        if ($hitcount) {
            $pk = $pk ?: (int) $this->getState('ip.id');

            $table = $this->getTable('Ip');
            $table->hit($pk);
        }

        return true;
    }

    public function saveIpData($ip)
    {
        $db     = $this->getDatabase();
        $ipData = new \stdClass();

        $ipData->ip             = $ip;
        $ipData->created_date   = Factory::getDate()->toSql();

        $query = $db->getQuery(true);
        $query->select('id')->from($db->qn('#__mail_ips'))
            ->where($db->qn('ip') . ' = ' . $db->q($ip))
            ->where($db->qn('member_id') . ' IS NULL');
        $db->setQuery($query);
        $currentIp = $db->loadResult();

        if($currentIp)
        {
            $ipData->id = $currentIp;

            if(!$db->updateObject('#__mail_ips', $ipData, 'id', true))
            {
                throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_CANNOT_SAVING_IP'), 404);
            }

            return true;
        }

        if(!$db->insertObject('#__mail_ips', $ipData))
        {
            throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_CANNOT_SAVING_IP'), 404);
        }

        return true;
    }

}
