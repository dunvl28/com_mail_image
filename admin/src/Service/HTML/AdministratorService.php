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
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Member HTML helper class.
 *
 * @since  1.6
 */
class AdministratorService
{
    /**
     * Get the associated language flags
     *
     * @param   integer  $memberId  The item id to search associations
     *
     * @return  string  The language HTML
     *
     * @throws  \Exception
     */
    public function association($memberId)
    {
        // Defaults
        $html = '';

        // Get the associations
        if ($associations = Associations::getAssociations('com_mail_image', '#__mail_members', 'com_mail_image.item', $memberId)) {
            foreach ($associations as $tag => $associated) {
                $associations[$tag] = (int) $associated->id;
            }

            // Get the associated member items
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(
                    [
                        $db->quoteName('c.id'),
                        $db->quoteName('c.name', 'title'),
                        $db->quoteName('l.sef', 'lang_sef'),
                        $db->quoteName('lang_code'),
                        $db->quoteName('l.image'),
                        $db->quoteName('l.title', 'language_title'),
                    ]
                )
                ->from($db->quoteName('#__mail_members', 'c'))
                ->join('LEFT', $db->quoteName('#__languages', 'l'), $db->quoteName('c.language') . ' = ' . $db->quoteName('l.lang_code'))
                ->whereIn($db->quoteName('c.id'), array_values($associations))
                ->where($db->quoteName('c.id') . ' != :id')
                ->bind(':id', $memberId, ParameterType::INTEGER);
            $db->setQuery($query);

            try {
                $items = $db->loadObjectList('id');
            } catch (\RuntimeException $e) {
                throw new \Exception($e->getMessage(), 500, $e);
            }

            if ($items) {
                $languages         = LanguageHelper::getContentLanguages([0, 1]);
                $content_languages = array_column($languages, 'lang_code');

                foreach ($items as &$item) {
                    if (in_array($item->lang_code, $content_languages)) {
                        $text    = $item->lang_code;
                        $url     = Route::_('index.php?option=com_mail_image&task=member.edit&id=' . (int) $item->id);
                        $tooltip = '<strong>' . htmlspecialchars($item->language_title, ENT_QUOTES, 'UTF-8') . '</strong><br>'
                            . htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8');
                        $classes = 'badge bg-secondary';

                        $item->link = '<a href="' . $url . '" class="' . $classes . '">' . $text . '</a>'
                            . '<div role="tooltip" id="tip-' . (int) $memberId . '-' . (int) $item->id . '">' . $tooltip . '</div>';
                    } else {
                        // Display warning if Content Language is trashed or deleted
                        Factory::getApplication()->enqueueMessage(Text::sprintf('JGLOBAL_ASSOCIATIONS_CONTENTLANGUAGE_WARNING', $item->lang_code), 'warning');
                    }
                }
            }

            $html = LayoutHelper::render('joomla.content.associations', $items);
        }

        return $html;
    }

    /**
     * Show the featured/not-featured icon.
     *
     * @param   integer  $value      The featured value.
     * @param   integer  $i          Id of the item.
     * @param   boolean  $canChange  Whether the value can be changed or not.
     *
     * @return  string  The anchor tag to toggle featured/unfeatured Memberss.
     *
     * @since   1.6
     */
    public function featured($value, $i, $canChange = true)
    {
        Factory::getDocument()->getWebAssetManager()->useScript('list-view');

        // Array of image, task, title, action
        $states = [
            0 => ['unfeatured', 'members.featured', 'COM_MAIL_IMAGE_UNFEATURED', 'JGLOBAL_ITEM_FEATURE'],
            1 => ['featured', 'members.unfeatured', 'JFEATURED', 'JGLOBAL_ITEM_UNFEATURE'],
        ];
        $state       = ArrayHelper::getValue($states, (int) $value, $states[1]);
        $icon        = $state[0] === 'featured' ? 'star featured' : 'circle';
        $tooltipText = Text::_($state[3]);

        if (!$canChange) {
            $tooltipText = Text::_($state[2]);
        }

        $html = '<button type="button" class="js-grid-item-action tbody-icon' . ($value == 1 ? ' active' : '') . '"'
            . ' aria-labelledby="cb' . $i . '-desc" data-item-id="cb' . $i . '" data-item-task="' .  $state[1] . '">'
            . '<span class="icon-' . $icon . '" aria-hidden="true"></span>'
            . '</button>'
            . '<div role="tooltip" id="cb' . $i . '-desc">' . $tooltipText . '</div>';

        return $html;
    }
}
