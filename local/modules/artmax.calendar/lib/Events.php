<?php
namespace Artmax\Calendar;

use Bitrix\Main\EventManager;

class Events
{
    private static $menuHandlerRegistered = false;

    /**
     * Регистрирует обработчики событий
     */
    public static function registerEvents()
    {
        try {
            $eventManager = EventManager::getInstance();
            
            // Регистрируем провайдер при загрузке страницы
            $eventManager->registerEventHandler(
                'main', 
                'OnPageStart', 
                'artmax.calendar',
                'Artmax\Calendar\EventHandlers', 
                'onPageStart'
            );

            // Регистрируем обработчик для добавления пункта в меню "Еще"
            $eventManager->registerEventHandler(
                'main', 
                'OnBuildGlobalMenu', 
                'artmax.calendar',
                __CLASS__, 
                'onBuildGlobalMenu'
            );
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_REGISTER_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка регистрации событий: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Обработчик события инициализации провайдеров настраиваемых разделов
     */
    public static function onCustomSectionProviderInit()
    {
        try {
            if (\CModule::IncludeModule('intranet')) {
                // Проверяем различные варианты класса менеджера
                if (class_exists('\Bitrix\Intranet\CustomSection\Provider\Manager')) {
                    \Bitrix\Intranet\CustomSection\Provider\Manager::registerProvider(
                        'artmax.calendar',
                        \Artmax\Calendar\Integration\Intranet\CustomSectionProvider::class
                    );
                } elseif (class_exists('\Bitrix\Intranet\CustomSection\ProviderManager')) {
                    \Bitrix\Intranet\CustomSection\ProviderManager::registerProvider(
                        'artmax.calendar',
                        \Artmax\Calendar\Integration\Intranet\CustomSectionProvider::class
                    );
                }
            }
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_PROVIDER_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка регистрации провайдера: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Обработчик события загрузки страницы
     */
    public static function onPageStart()
    {
        // Регистрируем провайдер при загрузке страницы
        self::onCustomSectionProviderInit();
        
        // Регистрируем обработчик для меню "Еще" при каждой загрузке страницы
        self::registerMenuEventHandler();
    }

    /**
     * Регистрирует обработчик для добавления пункта в меню "Еще"
     */
    public static function registerMenuEventHandler()
    {
        // Проверяем, не зарегистрирован ли уже обработчик
        if (self::$menuHandlerRegistered) {
            return;
        }

        try {
            $eventManager = EventManager::getInstance();
            
            // Регистрируем обработчик
            $eventManager->registerEventHandler(
                'main', 
                'OnBuildGlobalMenu', 
                'artmax.calendar',
                __CLASS__, 
                'onBuildGlobalMenu'
            );
            
            // Отмечаем, что обработчик зарегистрирован
            self::$menuHandlerRegistered = true;
            
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_MENU_REGISTER_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка регистрации обработчика меню: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Обработчик события построения глобального меню
     * Добавляет пункт "Создать филиал" в меню "Еще"
     */
    public static function onBuildGlobalMenu(&$aGlobalMenu, &$aModuleMenu)
    {
        try {
            // Проверяем, что модуль подключен
            if (!\CModule::IncludeModule('artmax.calendar')) {
                return;
            }

            // Добавляем пункт в меню "Еще" (global_menu_services)
            $aModuleMenu[] = [
                'parent_menu' => 'global_menu_services', // Раздел "Еще"
                'sort'        => 1000,                   // Порядок сортировки
                'text'        => 'Создать филиал',
                'title'       => 'Добавить новый филиал в календарь',
                'url'         => 'javascript:void(0)',   // Будет обработано JavaScript
                'icon'        => 'btn_new',              // Иконка кнопки
                'onclick'     => 'openAddBranchModal(); return false;', // JavaScript функция
            ];

        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_MENU_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка добавления пункта в меню: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Отменяет регистрацию обработчиков событий
     */
    public static function unregisterEvents()
    {
        try {
            $eventManager = EventManager::getInstance();
            
            // Отменяем регистрацию обработчика OnPageStart
            $eventManager->unRegisterEventHandler(
                'main', 
                'OnPageStart', 
                'artmax.calendar',
                'Artmax\Calendar\EventHandlers', 
                'onPageStart'
            );

            // Отменяем регистрацию обработчика OnBuildGlobalMenu
            $eventManager->unRegisterEventHandler(
                'main', 
                'OnBuildGlobalMenu', 
                'artmax.calendar',
                __CLASS__, 
                'onBuildGlobalMenu'
            );
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_UNREGISTER_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка отмены регистрации событий: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Проверяет, зарегистрирован ли обработчик события
     */
    private static function isEventHandlerRegistered($moduleId, $eventType, $className, $methodName): bool
    {
        try {
            // Используем рефлексию для проверки зарегистрированных обработчиков
            $eventManager = EventManager::getInstance();
            $reflection = new \ReflectionClass($eventManager);
            
            if ($reflection->hasProperty('handlers')) {
                $handlersProperty = $reflection->getProperty('handlers');
                $handlersProperty->setAccessible(true);
                $handlers = $handlersProperty->getValue($eventManager);
                
                return isset($handlers[$moduleId][$eventType]) && 
                       is_array($handlers[$moduleId][$eventType]) &&
                       !empty($handlers[$moduleId][$eventType]);
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
} 