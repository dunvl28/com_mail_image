<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Administrator\Controller;

require_once(JPATH_ADMINISTRATOR . '/components/com_mail_image/vendor/autoload.php');

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Component\ComponentHelper;
use stdClass;


// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects



/**
 * Members list controller class.
 *
 * @since  1.6
 */
class MembersController extends AdminController
{
    /**
     * Constructor.
     *
     * @param   array                $config   An optional associative array of configuration settings.
     * Recognized key values include 'name', 'default_task', 'model_path', and
     * 'view_path' (this list is not meant to be comprehensive).
     * @param   MVCFactoryInterface  $factory  The factory.
     * @param   CMSApplication       $app      The Application for the dispatcher
     * @param   Input                $input    Input
     *
     * @since   3.0
     */
    public function __construct($config = [], MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);




        $this->registerTask('unfeatured', 'featured');
    }

    /**
     * Method to toggle the featured setting of a list of Members.
     *
     * @return  void
     *
     * @since   1.6
     */
    public function featured()
    {
        // Check for request forgeries
        $this->checkToken();

        $ids    = (array) $this->input->get('cid', [], 'int');
        $values = ['featured' => 1, 'unfeatured' => 0];
        $task   = $this->getTask();
        $value  = ArrayHelper::getValue($values, $task, 0, 'int');

        // Get the model.
        /** @var \Joomla\Component\Mail_Image\Administrator\Model\MemberModel $model */
        $model  = $this->getModel();

        // Access checks.
        foreach ($ids as $i => $id) {
            // Remove zero value resulting from input filter
            if ($id === 0) {
                unset($ids[$i]);

                continue;
            }

            $item = $model->getItem($id);

            if (!$this->app->getIdentity()->authorise('core.edit.state', 'com_mail_image')) {
                // Prune items that you can't change.
                unset($ids[$i]);
                $this->app->enqueueMessage(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 'notice');
            }
        }

        if (empty($ids)) {
            $message = null;

            $this->app->enqueueMessage(Text::_('COM_MAIL_IMAGE_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Publish the items.
            if (!$model->featured($ids, $value)) {
                $this->app->enqueueMessage($model->getError(), 'warning');
            }

            if ($value == 1) {
                $message = Text::plural('COM_MAIL_IMAGE_N_ITEMS_FEATURED', count($ids));
            } else {
                $message = Text::plural('COM_MAIL_IMAGE_N_ITEMS_UNFEATURED', count($ids));
            }
        }

        $this->setRedirect('index.php?option=com_mail_image&view=members', $message);
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The name of the model.
     * @param   string  $prefix  The prefix for the PHP class name.
     * @param   array   $config  Array of configuration parameters.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   1.6
     */
    public function getModel($name = 'Member', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to get the number of published members for quickicons
     *
     * @return  void
     *
     * @since   4.3.0
     */
    public function getQuickiconContent()
    {
        $model = $this->getModel('members');

        $model->setState('filter.published', 1);

        $amount = (int) $model->getTotal();

        $result = [];

        $result['amount'] = $amount;
        $result['sronly'] = Text::plural('COM_MAIL_IMAGE_N_QUICKICON_SRONLY', $amount);
        $result['name']   = Text::plural('COM_MAIL_IMAGE_N_QUICKICON', $amount);

        echo new JsonResponse($result);
    }

    public function syncData()
    {
        $params = ComponentHelper::getParams('com_mail_image');

        $apiKey = $params->get('mailchimp_api_key','');
        $server = $params->get('mailchimp_server_prefix_id','');
        $limitIp = $params->get('number_ips_email',3);

        $model = $this->getModel('Members');

        if(!$apiKey || !$server)
        {
            $this->setRedirect('index.php?option=com_mail_image', Text::_('COM_MAIL_IMAGE_ERROR_MAILCHIMP_CONFIGURATION'), 'error');
            return;
        }

        $newIps = $model->getUnprocessedIps();

        if(empty($newIps))
        {
            $this->setRedirect('index.php?option=com_mail_image', Text::_('COM_MAIL_IMAGE_DATA_SYNC_NO_NEW_IP'));
            return;
        }

        $client = new \MailchimpMarketing\ApiClient();
        $client->setConfig([
            'apiKey' => $apiKey,
            'server' => $server,
        ]);

        $response = $client->searchMembers->search('*','full_search.members.email_address,full_search.members.ip_signup,full_search.members.ip_opt');

        if(empty($response->full_search->members))
        {
            return;
        }

        foreach ($newIps as $newIp)
        {
            $memberId = $this->getMemberId($response->full_search->members, $newIp->ip);
            $savedIps = $model->getIps($memberId,'*',false,'asc');

            if(count($savedIps) < $limitIp)
            {
                $model->updateIpOwner($newIp->id, $memberId);
            }
            else
            {
                $lastIp = $savedIps[0];
                $model->deleteIpOwner($lastIp->id);
                $model->updateIpOwner($newIp->id, $memberId, $newIp->ip);
            }

        }

        $this->setRedirect('index.php?option=com_mail_image', Text::_('COM_MAIL_IMAGE_DATA_SYNC_COMPLETED'));
    }

    private function getMemberId($members,$ip)
    {
        if(empty($members))
        {
            return 0;
        }

        $memberEmail = "";

        foreach ($members as $member)
        {
            if($member->ip_opt == $ip)
            {
                $memberEmail = $member->email_address;
                break;
            }
        }

        if(!$memberEmail)
        {
            return 0;
        }

        return $this->getModel('Members')->getMemberId($memberEmail);
    }

}
