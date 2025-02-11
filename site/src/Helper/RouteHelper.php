<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Site\Helper;

use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Language\Multilanguage;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Contact Component Route Helper
 *
 * @static
 * @package     Joomla.Site
 * @subpackage  com_mail_image
 * @since       1.5
 */
abstract class RouteHelper
{
    /**
     * Get the URL route for a ip from an ip ID and language
     *
     * @param   integer  $id        The id of the IP
     * @param   mixed    $language  The id of the language being used.
     *
     * @return  string  The link to the IP
     *
     * @since   1.5
     */
    public static function getIpRoute($id, $catid, $language = 0)
    {
        // Create the link
        $link = 'index.php?option=com_mail_image&view=ip&id=' . $id;

        if ($language && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        return $link;
    }
}
