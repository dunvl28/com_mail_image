<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


namespace Joomla\Component\Mail_Image\Administrator\Service\HTML;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
//use Joomla\Component\Mail_Image\Site\Helper\RouteHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content Component HTML Helper
 *
 * @since  4.0.0
 */
class Icon
{
    use UserFactoryAwareTrait;

    /**
     * Service constructor
     *
     * @param   UserFactoryInterface  $userFactory  The userFactory
     *
     * @since   4.0.0
     */
    public function __construct(UserFactoryInterface $userFactory)
    {
        $this->setUserFactory($userFactory);
    }

    /**
     * Method to generate a link to the create item page for the given category
     *
     * @param   Registry  $params    The item parameters
     * @param   array     $attribs   Optional attributes for the link
     *
     * @return  string  The HTML markup for the create item link
     *
     * @since  4.0.0
     */
    public function create($params, $attribs = [])
    {
        $uri = Uri::getInstance();

        $url = 'index.php?option=com_mail_image&task=member.add&return=' . base64_encode($uri) . '&id=0';

        $text = '';

        if ($params->get('show_icons')) {
            $text .= '<span class="icon-plus icon-fw" aria-hidden="true"></span>';
        }

        $text .= Text::_('COM_MAIL_IMAGE_NEW_MEMBER');

        // Add the button classes to the attribs array
        if (isset($attribs['class'])) {
            $attribs['class'] .= ' btn btn-primary';
        } else {
            $attribs['class'] = 'btn btn-primary';
        }

        $button = HTMLHelper::_('link', Route::_($url), $text, $attribs);

        return $button;
    }

    /**
     * Display an edit icon for the Member.
     *
     * This icon will not display in a popup window, nor if the Member is trashed.
     * Edit access checks must be performed in the calling code.
     *
     * @param   object    $member  The member information
     * @param   Registry  $params   The item parameters
     * @param   array     $attribs  Optional attributes for the link
     * @param   boolean   $legacy   True to use legacy images, false to use icomoon based graphic
     *
     * @return  string   The HTML for the member edit icon.
     *
     * @since   4.0.0
     */
    public function edit($member, $params, $attribs = [], $legacy = false)
    {
        $user = Factory::getUser();
        $uri  = Uri::getInstance();

        // Ignore if in a popup window.
        if ($params && $params->get('popup')) {
            return '';
        }

        // Ignore if the state is negative (trashed).
        if ($member->published < 0) {
            return '';
        }

        // Show checked_out icon if the member is checked out by a different user
        if (
            property_exists($member, 'checked_out')
            && property_exists($member, 'checked_out_time')
            && !is_null($member->checked_out)
            && $member->checked_out !== $user->get('id')
        ) {
            $checkoutUser = $this->getUserFactory()->loadUserById($member->checked_out);
            $date         = HTMLHelper::_('date', $member->checked_out_time);
            $tooltip      = Text::sprintf('COM_MAIL_IMAGE_CHECKED_OUT_BY', $checkoutUser->name)
                . ' <br> ' . $date;

            $text = LayoutHelper::render('joomla.content.icons.edit_lock', ['member' => $member, 'tooltip' => $tooltip, 'legacy' => $legacy]);

            $attribs['aria-describedby'] = 'editip-' . (int) $member->id;
            $output                      = HTMLHelper::_('link', '#', $text, $attribs);

            return $output;
        }

        $memberUrl = RouteHelper::getContactRoute($member->slug, $member->catid, $member->language);
        $url        = $memberUrl . '&task=member.edit&id=' . $member->id . '&return=' . base64_encode($uri);

        if ((int) $member->published === 0) {
            $tooltip = Text::_('COM_MAIL_IMAGE_EDIT_UNPUBLISHED_MEMBER');
        } else {
            $tooltip = Text::_('COM_MAIL_IMAGE_EDIT_PUBLISHED_MEMBER');
        }

        $nowDate = strtotime(Factory::getDate());
        $icon    = $member->published ? 'edit' : 'eye-slash';

        if (
            ($member->publish_up !== null && strtotime($member->publish_up) > $nowDate)
            || ($member->publish_down !== null && strtotime($member->publish_down) < $nowDate)
        ) {
            $icon = 'eye-slash';
        }

        $aria_described = 'editmember' . (int) $member->id;

        $text = '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
        $text .= Text::_('JGLOBAL_EDIT');
        $text .= '<div role="tooltip" id="' . $aria_described . '">' . $tooltip . '</div>';

        $attribs['aria-describedby'] = $aria_described;
        $output                      = HTMLHelper::_('link', Route::_($url), $text, $attribs);

        return $output;
    }
}
