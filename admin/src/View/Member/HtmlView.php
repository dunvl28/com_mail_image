<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_mail_image
 *
 * @copyright   (C) 2025 vuledunguyen@gmail.com
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Mail_Image\Administrator\View\Member;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View to edit a Member.
 *
 * @since  1.6
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The Form object
     *
     * @var  \Joomla\CMS\Form\Form
     */
    protected $form;

    /**
     * The active item
     *
     * @var  object
     */
    protected $item;

    /**
     * The model state
     *
     * @var  \Joomla\CMS\Object\CMSObject
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        // Initialise variables.
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        // If we are forcing a language in modal (used for associations).
        if ($this->getLayout() === 'modal' && $forcedLanguage = Factory::getApplication()->getInput()->get('forcedLanguage', '', 'cmd')) {
            // Set the language field to the forcedLanguage and disable changing it.
            $this->form->setValue('language', null, $forcedLanguage);
            $this->form->setFieldAttribute('language', 'readonly', 'true');

            // Only allow to select categories with All language or with the forced language.
            $this->form->setFieldAttribute('catid', 'language', '*,' . $forcedLanguage);

            // Only allow to select tags with All language or with the forced language.
            $this->form->setFieldAttribute('tags', 'language', '*,' . $forcedLanguage);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.6
     */
    protected function addToolbar()
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user       = $this->getCurrentUser();
        $userId     = $user->id;
        $isNew      = ($this->item->id == 0);
        $checkedOut = !(is_null($this->item->checked_out) || $this->item->checked_out == $userId);
        $toolbar    = Toolbar::getInstance();


        ToolbarHelper::title($isNew ? Text::_('COM_MAIL_IMAGE_MANAGER_MEMBER_NEW') : Text::_('COM_MAIL_IMAGE_MANAGER_MEMBER_EDIT'), 'users member');

        // Build the actions for new and existing records.
        if ($isNew) {
            // For new records, check the create permission.
            if ($user->authorise('core.create','com_mail_image' )) {
                $toolbar->apply('member.apply');

                $saveGroup = $toolbar->dropdownButton('save-group');

                $saveGroup->configure(
                    function (Toolbar $childBar) use ($user) {
                        $childBar->save('member.save');

                        if ($user->authorise('core.create', 'com_menus.menu')) {
                            $childBar->save('member.save2menu', 'JTOOLBAR_SAVE_TO_MENU');
                        }

                        $childBar->save2new('member.save2new');
                    }
                );
            }

            $toolbar->cancel('member.cancel');
        } else {
            // Since it's an existing record, check the edit permission, or fall back to edit own if the owner.
            $itemEditable = $user->authorise('core.edit','com_mail_image' );

            // Can't save the record if it's checked out and editable
            if (!$checkedOut && $itemEditable) {
                $toolbar->apply('member.apply');
            }

            $saveGroup = $toolbar->dropdownButton('save-group');

            $saveGroup->configure(
                function (Toolbar $childBar) use ($checkedOut, $itemEditable, $user) {
                    // Can't save the record if it's checked out and editable
                    if (!$checkedOut && $itemEditable) {
                        $childBar->save('member.save');

                        // We can save this record, but check the create permission to see if we can return to make a new one.
                        if ($user->authorise('core.create','com_mail_image' )) {
                            $childBar->save2new('member.save2new');
                        }
                    }

                    // If checked out, we can still save2menu
                    if ($user->authorise('core.create', 'com_menus.menu')) {
                        $childBar->save('member.save2menu', 'JTOOLBAR_SAVE_TO_MENU');
                    }

                    // If checked out, we can still save
                    if ($user->authorise('core.create','com_mail_image' )) {
                        $childBar->save2copy('member.save2copy');
                    }
                }
            );

            $toolbar->cancel('member.cancel');

            if (ComponentHelper::isEnabled('com_contenthistory') && $this->state->params->get('save_history', 0) && $itemEditable) {
                $toolbar->versions('com_mail_image.member', $this->item->id);
            }

        }

        $toolbar->divider();
    }
}
