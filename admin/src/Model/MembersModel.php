<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use stdClass;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Methods supporting a list of member records.
 *
 * @since  1.6
 */
class MembersModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since   1.6
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'alias', 'a.alias',
                'checked_out', 'a.checked_out',
                'checked_out_time', 'a.checked_out_time',
                'user_id', 'a.user_id',
                'published', 'a.published',
                'access', 'a.access', 'access_level',
                'created', 'a.created',
                'created_by', 'a.created_by',
                'ordering', 'a.ordering',
                'featured', 'a.featured',
                'language', 'a.language', 'language_title',
                'publish_up', 'a.publish_up',
                'publish_down', 'a.publish_down',
                'ul.name', 'linked_user',
                'tag',
                'level', 'c.level',
            ];

            if (Associations::isEnabled()) {
                $config['filter_fields'][] = 'association';
            }
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function populateState($ordering = 'a.name', $direction = 'asc')
    {
        $app = Factory::getApplication();

        $forcedLanguage = $app->getInput()->get('forcedLanguage', '', 'cmd');

        // Adjust the context to support modal layouts.
        if ($layout = $app->getInput()->get('layout')) {
            $this->context .= '.' . $layout;
        }

        // Adjust the context to support forced languages.
        if ($forcedLanguage) {
            $this->context .= '.' . $forcedLanguage;
        }

        // List state information.
        parent::populateState($ordering, $direction);

        // Force a language.
        if (!empty($forcedLanguage)) {
            $this->setState('filter.language', $forcedLanguage);
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since   1.6
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.access');
        $id .= ':' . $this->getState('filter.language');
        $id .= ':' . serialize($this->getState('filter.tag'));
        $id .= ':' . $this->getState('filter.level');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\DatabaseQuery
     *
     * @since   1.6
     */
    protected function getListQuery()
    {
        // Create a new query object.
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);
        $user  = $this->getCurrentUser();

        // Select the required fields from the table.
        $query->select(
            $db->quoteName(
                explode(
                    ', ',
                    $this->getState(
                        'list.select',
                        'a.id, a.name, a.alias, a.checked_out, a.checked_out_time, a.open, a.email' .
                        ', a.published, a.access, a.created, a.ordering, a.featured' .
                        ', a.publish_up, a.publish_down'
                    )
                )
            )
        );
        $query->from($db->quoteName('#__mail_members', 'a'));

        // Join over the users for the checked out user.
        $query->select($db->quoteName('uc.name', 'editor'))
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out')
            );

        // Join over the asset groups.
        $query->select($db->quoteName('ag.title', 'access_level'))
            ->join(
                'LEFT',
                $db->quoteName('#__viewlevels', 'ag') . ' ON ' . $db->quoteName('ag.id') . ' = ' . $db->quoteName('a.access')
            );

        // Filter by featured.
        $featured = (string) $this->getState('filter.featured');

        if (in_array($featured, ['0','1'])) {
            $query->where($db->quoteName('a.featured') . ' = ' . (int) $featured);
        }

        // Filter by access level.
        if ($access = $this->getState('filter.access')) {
            $query->where($db->quoteName('a.access') . ' = :access');
            $query->bind(':access', $access, ParameterType::INTEGER);
        }

        // Implement View Level Access
        if (!$user->authorise('core.admin')) {
            $query->whereIn($db->quoteName('a.access'), $user->getAuthorisedViewLevels());
        }

        // Filter by published state
        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = :published');
            $query->bind(':published', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where('(' . $db->quoteName('a.published') . ' = 0 OR ' . $db->quoteName('a.published') . ' = 1)');
        }

        // Filter by search in name.
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $search = substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id');
                $query->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . trim($search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.name') . ' LIKE :name OR ' . $db->quoteName('a.email') . ' LIKE :email)'
                );
                $query->bind(':name', $search);
                $query->bind(':email', $search);
            }
        }

        // Filter on the language.
        if ($language = $this->getState('filter.language')) {
            $query->where($db->quoteName('a.language') . ' = :language');
            $query->bind(':language', $language);
        }

        // Filter by a single or group of tags.
        $tag = $this->getState('filter.tag');

        // Run simplified query when filtering by one tag.
        if (\is_array($tag) && \count($tag) === 1) {
            $tag = $tag[0];
        }

        if ($tag && \is_array($tag)) {
            $tag = ArrayHelper::toInteger($tag);

            $subQuery = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('content_item_id'))
                ->from($db->quoteName('#__contentitem_tag_map'))
                ->where(
                    [
                        $db->quoteName('tag_id') . ' IN (' . implode(',', $query->bindArray($tag)) . ')',
                        $db->quoteName('type_alias') . ' = ' . $db->quote('com_mail_image.member'),
                    ]
                );

            $query->join(
                'INNER',
                '(' . $subQuery . ') AS ' . $db->quoteName('tagmap'),
                $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
            );
        } elseif ($tag = (int) $tag) {
            $query->join(
                'INNER',
                $db->quoteName('#__contentitem_tag_map', 'tagmap'),
                $db->quoteName('tagmap.content_item_id') . ' = ' . $db->quoteName('a.id')
            )
                ->where(
                    [
                        $db->quoteName('tagmap.tag_id') . ' = :tag',
                        $db->quoteName('tagmap.type_alias') . ' = ' . $db->quote('com_mail_image.member'),
                    ]
                )
                ->bind(':tag', $tag, ParameterType::INTEGER);
        }

        // Filter by and by level

        $level      = $this->getState('filter.level');

        if ($level) {
            // Case: Using only the by level filter
            $query->where($db->quoteName('c.level') . ' <= :level');
            $query->bind(':level', $level, ParameterType::INTEGER);
        }

        // Add the list ordering clause.
        $orderCol  = $this->state->get('list.ordering', 'a.name');
        $orderDirn = $this->state->get('list.direction', 'asc');

        if ($orderCol == 'a.ordering') {
            $orderCol = $db->quoteName('a.ordering');
        }

        $query->order($db->escape($orderCol . ' ' . $orderDirn));

        return $query;
    }

    public function getIps($memberId,$column = 'ip',$count = false,$order = "desc")
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('i.'.$column)->from($db->qn('#__mail_ips','i'))
            ->where($db->qn('i.member_id') . ' = ' . $db->quote($memberId))
            ->order($db->qn('i.ip').' '.$order);

        $db->setQuery($query);
        $list = $db->loadObjectList();

        if($count)
        {
            return count($list);
        }

        return $db->loadObjectList();
    }

    public function getUnprocessedIps()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('i.*')->from($db->qn('#__mail_ips','i'))
            ->where($db->qn('i.member_id') . ' IS NULL OR i.member_id = "" OR i.member_id = "0"')
            ->order($db->qn('i.ip').' desc');

        $db->setQuery($query);
        return $db->loadObjectList();
    }

    public function getMemberId($email)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $query->select('i.id')->from($db->qn('#__mail_members','i'))
            ->where($db->qn('i.email') . ' = ' . $db->quote($email));

        $db->setQuery($query);

        $memberId= $db->loadResult();

        if($memberId)
        {
            return $memberId;
        }

        $memberData = new stdClass();

        $memberData->email  = $email;
        $memberData->name   = $email;
        $memberData->alias  = $email;
        $memberData->published  = 1;
        $memberData->access  = 1;
        $memberData->created  = Factory::getDate()->toSql();
        $memberData->modified  = Factory::getDate()->toSql();

        if(!$db->insertObject('#__mail_members', $memberData))
        {
            throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_CANNOT_SAVING_MEMBER'), 404);
        }

        return $db->insertid();
    }

    public function updateIpOwner($ipId,$memberId,$ip = "")
    {
        $db     = $this->getDatabase();
        $ipData = new \stdClass();

        $ipData->id             = $ipId;
        $ipData->member_id      = $memberId;
        if($ip)
        {
            $ipData->ip = $ip;
        }

        if(!$db->updateObject('#__mail_ips', $ipData, 'id', true))
        {
            throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_CANNOT_SAVING_IP'), 404);
        }

        return true;
    }

    public function deleteIpOwner($ipId)
    {
        $db     = $this->getDatabase();
        $query  = $db->getQuery(true);

        $conditions = array(
            $db->qn('id') . ' = '.$ipId);

        $query->delete($db->qn('#__mail_ips'));
        $query->where($conditions);

        $db->setQuery($query);

        if(!$db->execute())
        {
            throw new \Exception(Text::_('COM_MAIL_IMAGE_ERROR_CANNOT_DELETE_IP'), 404);
        }

        return true;
    }

}
