<?php
namespace Artmax\Calendar;

use Bitrix\Main\EventManager;
use Bitrix\Main\Application;

class EventHandlers
{
    private static $pageStartHandlerRegistered = false;

    public static function onPageStart()
    {
        // Инициализация модуля при загрузке страницы
        if (!\CModule::IncludeModule('artmax.calendar')) {
            return;
        }

        // Регистрируем обработчик для меню "Еще"
        \Artmax\Calendar\Events::registerMenuEventHandler();

        // Подключение CSS и JS файлов
        $asset = \Bitrix\Main\Page\Asset::getInstance();
        //$asset->addCss('/local/components/artmax/calendar/templates/.default/style.css');
        //$asset->addJs('/local/components/artmax/calendar/templates/.default/script.js');
    }

    public static function onBeforeEventAdd(&$arFields)
    {
        // Обработка перед добавлением события
        if (!empty($arFields['TITLE'])) {
            $arFields['TITLE'] = trim($arFields['TITLE']);
        }
    }

    public static function onAfterEventAdd($ID, $arFields)
    {
        // Обработка после добавления события
        if ($ID > 0) {
            // Логирование или дополнительные действия
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_EVENT_ADD',
                'MODULE_ID' => 'artmax.calendar',
                'ITEM_ID' => $ID,
                'DESCRIPTION' => 'Добавлено новое событие: ' . $arFields['TITLE']
            ]);
        }
    }

    public static function onEpilog()
    {
        // Добавляем пункт меню только на страницах профиля пользователя
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        if ($request->isAjaxRequest()) return;
        
        $requestPage = $request->getRequestedPage();
        if (preg_match('@/company/personal/user/[0-9]+/@i', $requestPage)) {
            \Bitrix\Main\UI\Extension::load('artmax-calendar.add_menu_item');
        }

        // Модальное окно создания филиала больше не нужно,
        // так как форма теперь работает через SidePanel (компонент branch.form)
        // self::addBranchModalToAllPages();

        // Проверяем и создаем настраиваемый раздел, если его нет
        $customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
        if (!$customSectionId) {
            self::createCustomSection();
        }
    }

    /**
     * Метод addBranchModalToAllPages() удален.
     * Форма создания филиала теперь работает через SidePanel (компонент branch.form).
     */

    /**
     * Регистрирует события модуля при установке
     */
    public static function onModuleInstall()
    {
        \Artmax\Calendar\Events::registerEvents();
    }

    /**
     * Регистрирует обработчик OnPageStart для динамической регистрации событий
     */
    public static function registerPageStartHandler()
    {
        // Проверяем, не зарегистрирован ли уже обработчик
        if (self::$pageStartHandlerRegistered) {
            return;
        }

        try {
            $eventManager = \Bitrix\Main\EventManager::getInstance();
            
            // Регистрируем обработчик
            $eventManager->registerEventHandler(
                'main', 
                'OnPageStart', 
                'artmax.calendar',
                __CLASS__, 
                'onPageStart'
            );
            
            // Отмечаем, что обработчик зарегистрирован
            self::$pageStartHandlerRegistered = true;
            
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PAGE_START_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка регистрации OnPageStart: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Отменяет регистрацию событий модуля при удалении
     */
    public static function onModuleUninstall()
    {
        try {
            \Artmax\Calendar\Events::unregisterEvents();
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем удаление модуля
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_UNINSTALL_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка при удалении модуля: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Создает настраиваемый раздел и страницы для календаря
     */
    public static function createCustomSection()
    {
        if (!class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Класс CustomSectionTable не найден'
            ]);
            return false;
        }

        try {
            // Проверяем, не существует ли уже раздел
            $existingSection = \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::getList([
                'filter' => ['MODULE_ID' => 'artmax.calendar', 'CODE' => 'artmax_calendar']
            ])->fetch();

            if ($existingSection) {
                \Bitrix\Main\Config\Option::set('artmax.calendar', 'custom_section_id', $existingSection['ID']);
                
                // Проверяем, есть ли страницы у существующего раздела
                $pages = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
                    'filter' => ['CUSTOM_SECTION_ID' => $existingSection['ID']]
                ])->fetchAll();
                
                if (empty($pages)) {
                    // Создаем страницы для существующего раздела
                    self::createSectionPages($existingSection['ID']);
                }
                
                return true;
            }

            // Создаем раздел
            $sectionResult = \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::add([
                'CODE' => 'artmax_calendar',
                'TITLE' => 'Календарь ArtMax',
                'MODULE_ID' => 'artmax.calendar',
            ]);

            if (!$sectionResult->isSuccess()) {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'DESCRIPTION' => 'Ошибка создания раздела: ' . implode(', ', $sectionResult->getErrorMessages())
                ]);
                return false;
            }

            $sectionId = $sectionResult->getId();

            // Создаем страницы для раздела
            self::createSectionPages($sectionId);

            // Сохраняем ID раздела для последующего удаления
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'custom_section_id', $sectionId);

            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_CREATE',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Настраиваемый раздел календаря создан. ID: ' . $sectionId
            ]);

            return true;

        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка создания настраиваемого раздела: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Создает страницы для раздела
     */
    private static function createSectionPages($sectionId)
    {
        // Получаем список филиалов
        $branchObj = new \Artmax\Calendar\Branch();
        $branches = $branchObj->getBranches();

        // Создаем страницы для каждого филиала
        foreach ($branches as $branch) {
            self::createBranchPage($sectionId, $branch);
        }
    }

    /**
     * Создает страницу для конкретного филиала
     */
    public static function createBranchPage($sectionId, $branch)
    {
        // Проверяем, не существует ли уже страница
        $existingPage = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
            'filter' => [
                'CUSTOM_SECTION_ID' => $sectionId,
                'CODE' => 'calendar_branch_' . $branch['ID']
            ]
        ])->fetch();

        if (!$existingPage) {
            $pageResult = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::add([
                'CUSTOM_SECTION_ID' => $sectionId,
                'CODE' => 'calendar_branch_' . $branch['ID'],
                'TITLE' => 'Календарь - ' . $branch['NAME'],
                'MODULE_ID' => 'artmax.calendar',
                'SETTINGS' => $branch['ID'] . '~' . $branch['NAME'],
                'SORT' => $branch['ID'] * 10,
            ]);

            if (!$pageResult->isSuccess()) {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'DESCRIPTION' => 'Ошибка создания страницы для филиала ' . $branch['ID'] . ': ' . implode(', ', $pageResult->getErrorMessages())
                ]);
                return false;
            }

            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PAGE_CREATE',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Создана страница для филиала: ' . $branch['NAME'] . ' (ID: ' . $branch['ID'] . ')'
            ]);

            return true;
        }

        return true;
    }

    /**
     * Обновляет страницы раздела при добавлении нового филиала
     */
    public static function updateSectionPages()
    {
        $customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
        if (!$customSectionId) {
            return false;
        }

        // Получаем список филиалов
        $branchObj = new \Artmax\Calendar\Branch();
        $branches = $branchObj->getBranches();

        // Создаем страницы для всех филиалов
        foreach ($branches as $branch) {
            self::createBranchPage($customSectionId, $branch);
        }

        return true;
    }

    /**
     * Обновляет название страницы филиала в настраиваемом разделе
     */
    public static function updateBranchPageTitle($branchId, $newName)
    {
        try {
            $customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
            if (!$customSectionId) {
                return false;
            }

            // Находим страницу филиала
            $page = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
                'filter' => [
                    'CUSTOM_SECTION_ID' => $customSectionId,
                    'CODE' => 'calendar_branch_' . $branchId
                ]
            ])->fetch();

            if ($page) {
                // Обновляем название страницы
                $updateResult = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::update($page['ID'], [
                    'TITLE' => 'Календарь - ' . $newName,
                    'SETTINGS' => $branchId . '~' . $newName
                ]);

                if ($updateResult->isSuccess()) {
                    // Очищаем кеш настраиваемых разделов
                    try {
                        // Очищаем кеш через стандартные функции Bitrix
                        if (function_exists('BXClearCache')) {
                            BXClearCache(true, '/intranet/');
                        }
                        
                        // Очищаем кеш меню
                        if (function_exists('BXClearCache')) {
                            BXClearCache(true, '/menu/');
                        }
                        
                        // Очищаем кеш компонентов
                        if (function_exists('BXClearCache')) {
                            BXClearCache(true, '/bitrix/components/');
                        }
                        
                        // Принудительно обновляем кеш настраиваемых разделов
                        if (class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
                            // Просто обращаемся к таблице для обновления кеша
                            \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::getList([
                                'filter' => ['ID' => $customSectionId],
                                'select' => ['ID', 'TITLE']
                            ])->fetch();
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки очистки кеша
                        error_log('Ошибка очистки кеша настраиваемых разделов: ' . $e->getMessage());
                    }
                    
                    \CEventLog::Add([
                        'SEVERITY' => 'INFO',
                        'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PAGE_UPDATE',
                        'MODULE_ID' => 'artmax.calendar',
                        'DESCRIPTION' => 'Обновлено название страницы филиала: ' . $newName . ' (ID: ' . $branchId . ')'
                    ]);
                    return true;
                } else {
                    \CEventLog::Add([
                        'SEVERITY' => 'ERROR',
                        'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PAGE_UPDATE_ERROR',
                        'MODULE_ID' => 'artmax.calendar',
                        'DESCRIPTION' => 'Ошибка обновления страницы филиала: ' . implode(', ', $updateResult->getErrorMessages())
                    ]);
                }
            }

            return false;
        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PAGE_UPDATE_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка обновления названия страницы филиала: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Удаляет настраиваемый раздел календаря
     */
    public static function removeCustomSection()
    {
        if (!class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
            return false;
        }

        try {
            // Получаем ID раздела из настроек
            $sectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');

            if ($sectionId) {
                // Получаем все страницы раздела
                $pages = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
                    'filter' => ['CUSTOM_SECTION_ID' => $sectionId],
                    'select' => ['ID']
                ])->fetchAll();

                // Удаляем каждую страницу по отдельности
                foreach ($pages as $page) {
                    \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::delete($page['ID']);
                }

                // Удаляем сам раздел
                \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::delete($sectionId);

                // Удаляем ID из настроек
                \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'custom_section_id']);

                \CEventLog::Add([
                    'SEVERITY' => 'INFO',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_REMOVE',
                    'MODULE_ID' => 'artmax.calendar',
                    'DESCRIPTION' => 'Настраиваемый раздел календаря удален. ID: ' . $sectionId . ', страниц: ' . count($pages)
                ]);

                return true;
            }

        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SECTION_REMOVE_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка удаления настраиваемого раздела: ' . $e->getMessage()
            ]);
        }

        return false;
    }
} 