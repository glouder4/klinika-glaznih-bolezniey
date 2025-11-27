<?php
namespace Artmax\Calendar\Integration\Intranet;

use Bitrix\Intranet\CustomSection\Provider;
use Bitrix\Intranet\CustomSection\Provider\Component;
use Bitrix\Main\Web\Uri;

class CustomSectionProvider extends Provider
{


    /**
     * Проверяет доступность страницы для пользователя
     */
    public function isAvailable(string $pageSettings, int $userId): bool
    {
        try {
            // Парсим настройки страницы
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);
            
            // Проверяем существование филиала
            if ($branchId > 0) {
                if (class_exists('\Artmax\Calendar\Branch')) {
                    $branchObj = new \Artmax\Calendar\Branch();
                    $branch = $branchObj->getBranch($branchId);
                    return !empty($branch);
                }
            }
            
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

            /**
     * Возвращает параметры компонента для отображения
     */
    public function resolveComponent(string $pageSettings, Uri $url): ?\Bitrix\Intranet\CustomSection\Provider\Component
    {
        try {
            // Парсим настройки страницы
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);

            if ($branchId <= 0) {
                return null;
            }

            return (new Component())
                ->setComponentTemplate('.default')
                ->setComponentName('artmax:calendar')
                ->setComponentParams([
                    'CACHE_TYPE' => 'A',
                    'CACHE_TIME' => 3600,
                    'EVENTS_COUNT' => 20,
                    'SHOW_FORM' => 'Y',
                    'BRANCH_ID' => $branchId,
                ])
                ;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Возвращает ID счетчика для страницы
     */
    public function getCounterId(string $pageSettings): ?string
    {
        try {
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);
            
            if ($branchId > 0) {
                return 'artmax_calendar_events_' . $branchId;
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Возвращает значение счетчика для страницы
     * Считает только события с заполненным контактом на текущую дату
     */
    public function getCounterValue(string $pageSettings): ?int
    {
        try {
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);
            
            if ($branchId > 0 && class_exists('\Artmax\Calendar\Calendar')) {
                $calendar = new \Artmax\Calendar\Calendar();
                $userId = $GLOBALS['USER']->GetID();
                
                // Получаем текущую дату (сегодня) в формате Y-m-d
                // Метод getEventsByBranch сам добавит время (00:00:00 и 23:59:59)
                $today = date('Y-m-d');
                
                // Получаем события на текущую дату
                $events = $calendar->getEventsByBranch($branchId, $today, $today, $userId);
                
                // Фильтруем только события с заполненным контактом
                $eventsWithContact = array_filter($events, function($event) {
                    return !empty($event['CONTACT_ENTITY_ID']) && (int)$event['CONTACT_ENTITY_ID'] > 0;
                });
                
                return count($eventsWithContact);
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
} 