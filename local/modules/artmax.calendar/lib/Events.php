<?php
namespace Artmax\Calendar;

use Bitrix\Main\EventManager;

class Events
{
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
                __CLASS__, 
                'onPageStart'
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
                __CLASS__, 
                'onPageStart'
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