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
    public function resolveComponent(string $pageSettings, Uri $url): ?Component
    {
        try {
            // Парсим настройки страницы
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);
            
            if ($branchId <= 0) {
                return null;
            }

            // Проверяем существование компонента в local/components
            $componentPath = $_SERVER['DOCUMENT_ROOT'] . '/local/components/artmax/calendar/class.php';
            
            if (!file_exists($componentPath)) {
                return null;
            }

            // Возвращаем параметры для компонента календаря
            return new Component([
                'componentName' => 'artmax:calendar',
                'componentTemplate' => '.default',
                'params' => [
                    'CACHE_TYPE' => 'A',
                    'CACHE_TIME' => 3600,
                    'EVENTS_COUNT' => 20,
                    'SHOW_FORM' => 'Y',
                    'BRANCH_ID' => $branchId,
                ],
            ]);
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
     */
    public function getCounterValue(string $pageSettings): ?int
    {
        try {
            $params = explode('~', $pageSettings);
            $branchId = (int)($params[0] ?? 0);
            
            if ($branchId > 0 && class_exists('\Artmax\Calendar\Calendar')) {
                $calendar = new \Artmax\Calendar\Calendar();
                $userId = $GLOBALS['USER']->GetID();
                $events = $calendar->getEventsByBranch($branchId, null, null, $userId);
                return count($events);
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
} 