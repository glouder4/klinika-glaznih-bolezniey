<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ArtmaxCalendarComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!CModule::IncludeModule('artmax.calendar')) {
            ShowError('Модуль artmax.calendar не установлен');
            return;
        }

        // Получаем параметры
        $branchId = (int)($this->arParams['BRANCH_ID'] ?? 1);
        $eventsCount = (int)($this->arParams['EVENTS_COUNT'] ?? 20);
        $showForm = $this->arParams['SHOW_FORM'] === 'Y';

        // Получаем информацию о филиале
        $branchObj = new \Artmax\Calendar\Branch();
        $branch = $branchObj->getBranch($branchId);

        if (!$branch) {
            ShowError('Филиал не найден');
            return;
        }

        // Получаем события для филиала
        $calendarObj = new \Artmax\Calendar\Calendar();
        $events = $calendarObj->getEventsByBranch($branchId, null, null, null, $eventsCount);

        // Получаем список всех филиалов для навигации
        $allBranches = $branchObj->getBranches();

        // Формируем данные для шаблона
        $this->arResult = [
            'BRANCH' => $branch,
            'EVENTS' => $events,
            'ALL_BRANCHES' => $allBranches,
            'SHOW_FORM' => $showForm,
            'CURRENT_USER_ID' => $GLOBALS['USER']->GetID(),
            'CAN_ADD_EVENTS' => $GLOBALS['USER']->IsAuthorized(),
        ];

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }

    /**
     * Обработка AJAX запросов
     */
    public function configureActions()
    {
        return [
            'addEvent' => [
                'prefilters' => [],
                'postfilters' => []
            ],
            'deleteEvent' => [
                'prefilters' => [],
                'postfilters' => []
            ],
            'getEvents' => [
                'prefilters' => [],
                'postfilters' => []
            ]
        ];
    }

    /**
     * Добавление события
     */
    public function addEventAction($title, $description, $dateFrom, $dateTo, $branchId)
    {
        if (!$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $GLOBALS['USER']->GetID();

            // Проверяем доступность времени
            if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId)) {
                return ['success' => false, 'error' => 'Время уже занято'];
            }

            $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId);

            if ($eventId) {
                return ['success' => true, 'eventId' => $eventId];
            } else {
                return ['success' => false, 'error' => 'Ошибка добавления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Удаление события
     */
    public function deleteEventAction($eventId)
    {
        if (!$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            $calendarObj = new \Artmax\Calendar\Calendar();
            $event = $calendarObj->getEvent($eventId);

            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на удаление (только автор события)
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на удаление'];
            }

            $result = $calendarObj->deleteEvent($eventId);

            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка удаления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение событий
     */
    public function getEventsAction($branchId, $dateFrom = null, $dateTo = null)
    {
        try {
            $calendarObj = new \Artmax\Calendar\Calendar();
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo);

            return ['success' => true, 'events' => $events];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
} 