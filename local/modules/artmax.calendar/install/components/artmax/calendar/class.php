<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Calendar;

Loc::loadMessages(__FILE__);

class ArtmaxCalendarComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!CModule::IncludeModule('artmax.calendar')) {
            ShowError('Модуль artmax.calendar не установлен');
            return;
        }

        $this->arResult = [];
        $this->arParams = array_merge($this->arParams, [
            'CACHE_TYPE' => $this->arParams['CACHE_TYPE'] ?? 'A',
            'CACHE_TIME' => $this->arParams['CACHE_TIME'] ?? 3600,
            'EVENTS_COUNT' => $this->arParams['EVENTS_COUNT'] ?? 10,
            'SHOW_FORM' => $this->arParams['SHOW_FORM'] ?? 'Y',
        ]);

        // Получаем список филиалов
        $branchObj = new \Artmax\Calendar\Branch();
        $branches = $branchObj->getBranches();
        $this->arResult['BRANCHES'] = $branches;

        // Получаем выбранный филиал
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['ID'] ?? null);
        $this->arResult['SELECTED_BRANCH_ID'] = $branchId;

        if ($this->startResultCache()) {
            $this->processRequest();
            $this->getEvents($branchId);
            $this->includeComponentTemplate();
        }
    }

    private function processRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'add_event') {
                $this->addEvent();
            } elseif ($action === 'delete_event') {
                $this->deleteEvent();
            }
        }
    }

    private function addEvent()
    {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dateFrom = $_POST['date_from'] ?? '';
        $dateTo = $_POST['date_to'] ?? '';
        $userId = $GLOBALS['USER']->GetID();

        if (empty($title) || empty($dateFrom) || empty($dateTo)) {
            $this->arResult['ERROR'] = 'Все обязательные поля должны быть заполнены';
            return;
        }

        $calendar = new Calendar();
        
        if ($calendar->addEvent($title, $description, $dateFrom, $dateTo, $userId)) {
            $this->arResult['SUCCESS'] = 'Событие успешно добавлено';
        } else {
            $this->arResult['ERROR'] = 'Ошибка при добавлении события';
        }
    }

    private function deleteEvent()
    {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $userId = $GLOBALS['USER']->GetID();

        if ($eventId > 0) {
            $calendar = new Calendar();
            $event = $calendar->getEvent($eventId);
            
            if ($event && $event['USER_ID'] == $userId) {
                if ($calendar->deleteEvent($eventId)) {
                    $this->arResult['SUCCESS'] = 'Событие успешно удалено';
                } else {
                    $this->arResult['ERROR'] = 'Ошибка при удалении события';
                }
            } else {
                $this->arResult['ERROR'] = 'Нет прав для удаления этого события';
            }
        }
    }

    private function getEvents($branchId = null)
    {
        $calendar = new \Artmax\Calendar\Calendar();
        $userId = $GLOBALS['USER']->GetID();
        if ($branchId) {
            $this->arResult['EVENTS'] = $calendar->getEventsByBranch($branchId, null, null, $userId);
        } else {
            $this->arResult['EVENTS'] = [];
        }
        $this->arResult['USER_ID'] = $userId;
    }
} 