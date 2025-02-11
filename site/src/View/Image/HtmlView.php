<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Site\View\Image;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * HTML Image View class for the Mail_Image component
 *
 * @since  1.5
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The item model state
     *
     * @var    \Joomla\Registry\Registry
     *
     * @since  1.6
     */
    protected $state;

    /**
     * The form object for the image item
     *
     * @var    \Joomla\CMS\Form\Form
     *
     * @since  1.6
     */
    protected $form;

    /**
     * The item object details
     *
     * @var    \Joomla\CMS\Object\CMSObject
     *
     * @since  1.6
     */
    protected $item;

    /**
     * The page to return to on submission
     *
     * @var    string
     *
     * @since  1.6
     *
     * @todo Implement this functionality
     */
    protected $return_page = '';


    /**
     * The page parameters
     *
     * @var    \Joomla\Registry\Registry|null
     *
     * @since  4.0.0
     */
    protected $params = null;

    /**
     * The user object
     *
     * @var    \Joomla\CMS\User\User
     *
     * @since  4.0.0
     */
    protected $user;


    /**
     * The page class suffix
     *
     * @var    string
     *
     * @since  4.0.0
     */
    protected $pageclass_sfx = '';


    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void|boolean
     */
    public function display($tpl = null)
    {

        $state      = $this->get('State');
        $item       = $this->get('Item');
        $this->form = $this->get('Form');

        // Fix for where some plugins require a text attribute
        $item->text = '';

        if (!empty($item->misc)) {
            $item->text = $item->misc;
        }

        if (!empty($item->text)) {
            $item->misc = $item->text;
        }


        // Escape strings for HTML output
        $this->pageclass_sfx = htmlspecialchars($item->params->get('pageclass_sfx', ''));

        $this->params      = &$item->params;
        $this->state       = &$state;
        $this->item        = &$item;

        parent::display($tpl);
    }
}
