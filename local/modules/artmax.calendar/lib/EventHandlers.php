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

        // Добавляем модальное окно создания филиала на все страницы
        self::addBranchModalToAllPages();

        // Проверяем и создаем настраиваемый раздел, если его нет
        $customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
        if (!$customSectionId) {
            self::createCustomSection();
        }
    }

    /**
     * Добавляет модальное окно создания филиала на все страницы
     */
    public static function addBranchModalToAllPages()
    {
        try {
            // Добавляем HTML модального окна
            echo '<div id="addBranchModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Создать новый филиал</h2>
                        <span class="close" onclick="closeAddBranchModal()">&times;</span>
                    </div>
                    <form id="addBranchForm">
                        <div class="form-group">
                            <label for="branchName">Название филиала *</label>
                            <input type="text" id="branchName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="branchAddress">Адрес</label>
                            <input type="text" id="branchAddress" name="address">
                        </div>
                        <div class="form-group">
                            <label for="branchPhone">Телефон</label>
                            <input type="text" id="branchPhone" name="phone">
                        </div>
                        <div class="form-group">
                            <label for="branchEmail">Email</label>
                            <input type="email" id="branchEmail" name="email">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeAddBranchModal()">Отмена</button>
                            <button type="submit" class="btn btn-primary">Создать филиал</button>
                        </div>
                    </form>
                </div>
            </div>';

            // Добавляем CSS стили
            echo '<style>
                .modal {
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                }
                .modal-content {
                    background-color: #fefefe;
                    margin: 5% auto;
                    padding: 0;
                    border: none;
                    width: 90%;
                    max-width: 500px;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .modal-header h2 {
                    margin: 0;
                    color: #333;
                }
                .close {
                    color: #aaa;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                .close:hover {
                    color: #000;
                }
                #addBranchForm {
                    padding: 20px;
                }
                .form-group {
                    margin-bottom: 15px;
                }
                .form-group label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                    color: #333;
                }
                .form-group input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                .modal-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    margin-top: 20px;
                }
                .btn {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .btn-primary {
                    background-color: #007bff;
                    color: white;
                }
                .btn-secondary {
                    background-color: #6c757d;
                    color: white;
                }
                .btn:hover {
                    opacity: 0.9;
                }
            </style>';

            // Добавляем JavaScript функции
            echo '<script>
                function openAddBranchModal() {
                    document.getElementById("addBranchModal").style.display = "block";
                }
                
                function closeAddBranchModal() {
                    document.getElementById("addBranchModal").style.display = "none";
                    document.getElementById("addBranchForm").reset();
                }
                
                // Закрытие модального окна при клике вне его
                window.onclick = function(event) {
                    var modal = document.getElementById("addBranchModal");
                    if (event.target == modal) {
                        closeAddBranchModal();
                    }
                }
                
                // Обработка отправки формы
                document.addEventListener("DOMContentLoaded", function() {
                    var form = document.getElementById("addBranchForm");
                    if (form) {
                        form.addEventListener("submit", function(event) {
                            event.preventDefault();
                            
                            var formData = new FormData(form);
                            var branchData = {
                                name: formData.get("name"),
                                address: formData.get("address"),
                                phone: formData.get("phone"),
                                email: formData.get("email")
                            };
                            
                            // Отправляем AJAX запрос
                            fetch("/local/components/artmax/calendar/ajax.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded",
                                },
                                body: "action=addBranch&" + new URLSearchParams(branchData) + "&sessid=" + BX.bitrix_sessid()
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert("Филиал успешно создан! Переключатель филиалов обновится автоматически.");
                                    closeAddBranchModal();
                                    // Перезагружаем страницу для обновления меню
                                    window.location.reload();
                                } else {
                                    alert("Ошибка создания филиала: " + (data.error || "Неизвестная ошибка"));
                                }
                            })
                            .catch(error => {
                                console.error("Ошибка при создании филиала:", error);
                                alert("Ошибка соединения с сервером");
                            });
                        });
                    }
                });
            </script>';

        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_MODAL_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'DESCRIPTION' => 'Ошибка добавления модального окна: ' . $e->getMessage()
            ]);
        }
    }

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