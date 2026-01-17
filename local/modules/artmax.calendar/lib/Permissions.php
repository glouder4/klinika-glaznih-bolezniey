<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;

/**
 * Класс для работы с правами доступа и группами пользователей
 */
class Permissions
{
    private $connection;
    private $sqlHelper;

    public function __construct()
    {
        try {
            $this->connection = Application::getConnection();
            $this->sqlHelper = $this->connection->getSqlHelper();
        } catch (\Exception $e) {
            artmax_log('Permissions constructor error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получить все права доступа
     */
    public function getAllPermissions()
    {
        // Проверяем существование таблицы
        if (!$this->tableExists('artmax_calendar_permissions')) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM artmax_calendar_permissions ORDER BY CODE ASC";
            $result = $this->connection->query($sql);
            
            $permissions = [];
            while ($row = $result->fetch()) {
                $permissions[] = $row;
            }
            
            return $permissions;
        } catch (\Exception $e) {
            error_log('Ошибка получения прав доступа: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить все группы календаря
     */
    public function getCalendarGroups()
    {
        // Проверяем существование таблицы
        if (!$this->tableExists('artmax_calendar_user_groups')) {
            return [];
        }
        
        try {
            $sql = "
            SELECT DISTINCT ug.GROUP_ID, ug.GROUP_NAME,
                   CASE WHEN ug.GROUP_ID < 0 THEN 'Y' ELSE g.ACTIVE END as ACTIVE
            FROM artmax_calendar_user_groups ug
            LEFT JOIN b_group g ON g.ID = ug.GROUP_ID AND ug.GROUP_ID > 0
            WHERE ug.USER_ID IS NULL
            ORDER BY ug.GROUP_NAME ASC
            ";
            $result = $this->connection->query($sql);

            $groups = [];
            while ($row = $result->fetch()) {
                $groups[] = $row;
            }

            return $groups;
        } catch (\Exception $e) {
            artmax_log('Ошибка получения групп календаря: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Создать новую группу пользователей
     */
    public function createGroup($name, $description = '')
    {
        try {
        global $USER;
        
        // Проверяем, что объект USER инициализирован
        if (!$USER || !is_object($USER)) {
            artmax_log('createGroup: USER object not initialized');
            return [
                'success' => false,
                'error' => 'Объект пользователя не инициализирован'
            ];
        }
        
        $userId = $USER->GetID();
        artmax_log('createGroup: Starting creation of group "' . $name . '" by user ' . $userId);

        // Проверяем права на создание групп
        if (!$USER->IsAdmin() && !$USER->CanDoOperation('edit_groups')) {
            artmax_log('createGroup: User does not have permission to create groups');
            return [
                    'success' => false,
                    'error' => 'У вас нет прав на создание групп пользователей'
                ];
            }

        // СНАЧАЛА проверяем, есть ли группа с таким именем уже в таблице календаря
        // Это важнее, чем проверка в Bitrix, т.к. пользователь создает группу для календаря
        $sqlCheckCalendar = "
        SELECT ID, GROUP_ID, GROUP_NAME 
        FROM artmax_calendar_user_groups 
        WHERE GROUP_NAME = '" . $this->sqlHelper->forSql($name) . "' AND USER_ID IS NULL
        LIMIT 1
        ";
        $existingCalendarGroup = null;
        try {
            $existingCalendarGroup = $this->connection->query($sqlCheckCalendar)->fetch();
        } catch (\Exception $e) {
            artmax_log('createGroup: Error checking calendar groups table: ' . $e->getMessage());
        }
        
        if ($existingCalendarGroup) {
            artmax_log('createGroup: Group already exists in calendar groups table with ID ' . $existingCalendarGroup['GROUP_ID']);
            return [
                'success' => false,
                'error' => 'Группа с таким названием уже существует в календаре'
            ];
        }
        
        // Проверяем, есть ли группа с таким именем в Bitrix
        // Если есть - это проблема, т.к. мы не можем создать новую группу с таким же именем
        // ВАЖНО: CGroup::GetList может искать по частичному совпадению, поэтому проверяем точное совпадение имени
        $existingBitrixGroup = \CGroup::GetList(
            $by = 'ID',
            $order = 'ASC',
            ['NAME' => $name]
        )->Fetch();

        if ($existingBitrixGroup) {
            // Проверяем, что имя найденной группы ТОЧНО совпадает с запрашиваемым
            // CGroup::GetList может использовать LIKE поиск, поэтому нужна дополнительная проверка
            $foundGroupName = trim($existingBitrixGroup['NAME'] ?? '');
            $requestedName = trim($name);
            
            artmax_log('createGroup: Found Bitrix group with ID ' . $existingBitrixGroup['ID'] . ', name: "' . $foundGroupName . '", requested: "' . $requestedName . '"');
            
            if ($foundGroupName === $requestedName) {
                artmax_log('createGroup: Group with exact name "' . $name . '" already exists in Bitrix with ID ' . $existingBitrixGroup['ID']);
                return [
                    'success' => false,
                    'error' => 'Группа с таким названием уже существует в Bitrix. Пожалуйста, выберите другое название для новой группы календаря.'
                ];
            } else {
                artmax_log('createGroup: Found group has different name ("' . $foundGroupName . '" != "' . $requestedName . '"), continuing with new group creation');
                // Имя не совпадает точно - это не та группа, продолжаем создание
            }
        }

        // Создаем новую группу
        $groupObj = new \CGroup();

        // Формируем STRING_ID для группы (должен быть уникальным и без спецсимволов)
        $baseStringId = 'artmax_calendar_' . mb_strtolower(str_replace([' ', '|', '-'], ['_', '_', '_'], $name));
        $stringId = preg_replace('/[^a-z0-9_]/', '', $baseStringId);

        // Проверяем уникальность STRING_ID
        $counter = 0;
        $originalStringId = $stringId;
        while (\CGroup::GetList($by = 'ID', $order = 'ASC', ['STRING_ID' => $stringId])->Fetch()) {
            $counter++;
            $stringId = $originalStringId . '_' . $counter;
        }

        artmax_log('createGroup: Generated STRING_ID: ' . $stringId);

        // Создаем группу с обязательными полями
        $fields = [
            'ACTIVE' => 'Y',
            'C_SORT' => 100,
            'NAME' => $name,
            'DESCRIPTION' => $description ?: '',
            'STRING_ID' => $stringId
        ];

        artmax_log('createGroup: Calling CGroup::Add with fields: ' . json_encode($fields, JSON_UNESCAPED_UNICODE));

        try {
            $groupId = $groupObj->Add($fields);
        } catch (\Exception $e) {
            artmax_log('createGroup: CGroup::Add exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Ошибка при вызове CGroup::Add: ' . $e->getMessage()
            ];
        }

        artmax_log('createGroup: CGroup::Add returned: ' . var_export($groupId, true));

        if (!$groupId) {
            $lastError = $groupObj->LAST_ERROR ?: 'Неизвестная ошибка';
            artmax_log('createGroup: CGroup::Add failed with error: ' . $lastError);

            // Если не можем создать группу в Bitrix, создадим виртуальную группу
            // Используем отрицательный ID для виртуальных групп
            $virtualGroupId = time() * -1; // Отрицательный timestamp
            artmax_log('createGroup: Creating virtual group with ID: ' . $virtualGroupId);

            $groupId = $virtualGroupId;
        }

        artmax_log('createGroup: Group created successfully with ID: ' . $groupId);
        
        // Сохраняем информацию о группе в таблицу
        try {
            $sqlInsert = "
            INSERT INTO artmax_calendar_user_groups (GROUP_ID, GROUP_NAME, USER_ID) 
            VALUES (" . (int)$groupId . ", '" . $this->sqlHelper->forSql($name) . "', NULL)
            ";
            $this->connection->query($sqlInsert);
            
            return [
                'success' => true,
                'groupId' => $groupId,
                'groupName' => $name
            ];
        } catch (\Exception $e) {
            artmax_log('createGroup: Database error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Ошибка сохранения информации о группе: ' . $e->getMessage()
            ];
        }
        } catch (\Exception $e) {
            artmax_log('createGroup: Fatal error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Критическая ошибка при создании группы: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Удалить группу (только из таблицы календаря, не из Bitrix)
     */
    public function deleteGroup($groupId)
    {
        try {
            // Удаляем все права группы
            $sqlDeleteRights = "
            DELETE FROM artmax_calendar_access_rights 
            WHERE ENTITY_TYPE = 'group' AND ENTITY_ID = " . (int)$groupId . "
            ";
            $this->connection->query($sqlDeleteRights);
            
            // Удаляем информацию о группе
            $sqlDeleteGroup = "
            DELETE FROM artmax_calendar_user_groups 
            WHERE GROUP_ID = " . (int)$groupId . " AND USER_ID IS NULL
            ";
            $this->connection->query($sqlDeleteGroup);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка удаления группы: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Получить права группы
     */
    public function getGroupPermissions($groupId)
    {
        $sql = "
        SELECT p.* 
        FROM artmax_calendar_access_rights ar
        INNER JOIN artmax_calendar_permissions p ON p.ID = ar.PERMISSION_ID
        WHERE ar.ENTITY_TYPE = 'group' AND ar.ENTITY_ID = " . (int)$groupId . "
        ORDER BY p.CODE ASC
        ";
        $result = $this->connection->query($sql);
        
        $permissions = [];
        while ($row = $result->fetch()) {
            $permissions[] = $row;
        }
        
        return $permissions;
    }

    /**
     * Получить права пользователя (включая права из групп)
     */
    public function getUserPermissions($userId)
    {
        // Получаем права, назначенные напрямую пользователю
        $sqlDirect = "
        SELECT p.* 
        FROM artmax_calendar_access_rights ar
        INNER JOIN artmax_calendar_permissions p ON p.ID = ar.PERMISSION_ID
        WHERE ar.ENTITY_TYPE = 'user' AND ar.ENTITY_ID = " . (int)$userId . "
        ";
        $result = $this->connection->query($sqlDirect);
        
        $permissions = [];
        while ($row = $result->fetch()) {
            $permissions[$row['CODE']] = $row;
        }
        
        // Получаем группы пользователя через CUser::GetUserGroup
        $userGroups = \CUser::GetUserGroup($userId);
        
        // Получаем права из групп
        foreach ($userGroups as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId > 0) {
                $groupPermissions = $this->getGroupPermissions($groupId);
                foreach ($groupPermissions as $perm) {
                    if (!isset($permissions[$perm['CODE']])) {
                        $permissions[$perm['CODE']] = $perm;
                    }
                }
            }
        }
        
        return array_values($permissions);
    }

    /**
     * Назначить права группе
     */
    public function assignPermissionsToGroup($groupId, $permissionIds)
    {
        try {
            // Удаляем старые права группы
            $sqlDelete = "
            DELETE FROM artmax_calendar_access_rights 
            WHERE ENTITY_TYPE = 'group' AND ENTITY_ID = " . (int)$groupId . "
            ";
            $this->connection->query($sqlDelete);
            
            // Добавляем новые права
            if (!empty($permissionIds)) {
                foreach ($permissionIds as $permissionId) {
                    $permissionId = (int)$permissionId;
                    if ($permissionId > 0) {
                        $sqlInsert = "
                        INSERT INTO artmax_calendar_access_rights (PERMISSION_ID, ENTITY_TYPE, ENTITY_ID) 
                        VALUES (" . $permissionId . ", 'group', " . (int)$groupId . ")
                        ";
                        $this->connection->query($sqlInsert);
                    }
                }
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка назначения прав: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Назначить права пользователю
     */
    public function assignPermissionsToUser($userId, $permissionIds)
    {
        try {
            // Удаляем старые права пользователя
            $sqlDelete = "
            DELETE FROM artmax_calendar_access_rights 
            WHERE ENTITY_TYPE = 'user' AND ENTITY_ID = " . (int)$userId . "
            ";
            $this->connection->query($sqlDelete);
            
            // Добавляем новые права
            if (!empty($permissionIds)) {
                foreach ($permissionIds as $permissionId) {
                    $permissionId = (int)$permissionId;
                    if ($permissionId > 0) {
                        $sqlInsert = "
                        INSERT INTO artmax_calendar_access_rights (PERMISSION_ID, ENTITY_TYPE, ENTITY_ID) 
                        VALUES (" . $permissionId . ", 'user', " . (int)$userId . ")
                        ";
                        $this->connection->query($sqlInsert);
                    }
                }
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка назначения прав: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Проверить, есть ли у пользователя право
     */
    public function hasPermission($userId, $permissionCode)
    {
        // Проверяем существование необходимых таблиц
        if (!$this->tableExists('artmax_calendar_permissions') || !$this->tableExists('artmax_calendar_access_rights')) {
            // Если таблицы не созданы, возвращаем false (нет прав)
            error_log('hasPermission: Tables do not exist');
            return false;
        }
        
        try {
            // Проверяем прямое назначение
            $sql = "
            SELECT COUNT(*) as cnt
            FROM artmax_calendar_access_rights ar
            INNER JOIN artmax_calendar_permissions p ON p.ID = ar.PERMISSION_ID
            WHERE ar.ENTITY_TYPE = 'user' 
            AND ar.ENTITY_ID = " . (int)$userId . "
            AND p.CODE = '" . $this->sqlHelper->forSql($permissionCode) . "'
            ";
            $result = $this->connection->query($sql);
            $row = $result->fetch();
            
            if ($row && $row['cnt'] > 0) {
                error_log('hasPermission: User ' . $userId . ' has permission ' . $permissionCode . ' directly');
                return true;
            }
            
            // Проверяем через группы
            $userGroups = \CUser::GetUserGroup($userId);
            error_log('hasPermission: Checking groups for user ' . $userId . ' with permission ' . $permissionCode . ', groups: ' . implode(', ', $userGroups));
            
            foreach ($userGroups as $groupId) {
                $groupId = (int)$groupId;
                if ($groupId > 0) {
                    $sqlGroup = "
                    SELECT COUNT(*) as cnt
                    FROM artmax_calendar_access_rights ar
                    INNER JOIN artmax_calendar_permissions p ON p.ID = ar.PERMISSION_ID
                    WHERE ar.ENTITY_TYPE = 'group' 
                    AND ar.ENTITY_ID = " . $groupId . "
                    AND p.CODE = '" . $this->sqlHelper->forSql($permissionCode) . "'
                    ";
                    $resultGroup = $this->connection->query($sqlGroup);
                    $rowGroup = $resultGroup->fetch();
                    
                    error_log('hasPermission: Group ' . $groupId . ' has permission ' . $permissionCode . ': ' . ($rowGroup && $rowGroup['cnt'] > 0 ? 'YES (cnt=' . $rowGroup['cnt'] . ')' : 'NO'));
                    
                    if ($rowGroup && $rowGroup['cnt'] > 0) {
                        error_log('hasPermission: User ' . $userId . ' has permission ' . $permissionCode . ' through group ' . $groupId);
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            // В случае ошибки возвращаем false (нет прав)
            error_log('Ошибка проверки прав доступа: ' . $e->getMessage());
            error_log('hasPermission: Exception trace: ' . $e->getTraceAsString());
            return false;
        }
        
        error_log('hasPermission: User ' . $userId . ' does NOT have permission ' . $permissionCode);
        return false;
    }

    /**
     * Добавить пользователя в группу Bitrix
     */
    public function addUserToGroup($userId, $groupId)
    {
        $user = new \CUser();
        $user->Update($userId, ['GROUP_ID' => [$groupId]]);
        
        if ($user->LAST_ERROR) {
            return [
                'success' => false,
                'error' => $user->LAST_ERROR
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Удалить пользователя из группы Bitrix
     */
    public function removeUserFromGroup($userId, $groupId)
    {
        $user = \CUser::GetByID($userId)->Fetch();
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Пользователь не найден'
            ];
        }
        
        $userGroups = explode(',', $user['GROUP_ID']);
        $userGroups = array_filter($userGroups, function($id) use ($groupId) {
            return (int)trim($id) != (int)$groupId;
        });
        
        $userObj = new \CUser();
        $userObj->Update($userId, ['GROUP_ID' => array_values($userGroups)]);
        
        if ($userObj->LAST_ERROR) {
            return [
                'success' => false,
                'error' => $userObj->LAST_ERROR
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Получить все группы Bitrix (для привязки к группам календаря)
     * Исключает группы, созданные календарем
     */
    public function getAllBitrixGroups()
    {
        try {
            $groups = [];
            
            $rsGroups = \CGroup::GetList($by = 'ID', $order = 'ASC');
            
            if (!$rsGroups) {
                artmax_log('Ошибка: CGroup::GetList вернул false');
                return [];
            }
            
            while ($group = $rsGroups->Fetch()) {
                if ($group && isset($group['ID'])) {
                    $groupId = (int)$group['ID'];
                    $groupName = $group['NAME'] ?? '';
                    $stringId = $group['STRING_ID'] ?? '';
                    
                    // Исключаем группу "Все пользователи (в том числе неавторизованные)"
                    // Обычно это группа с ID = 2 или с таким названием
                    if ($groupId == 2 || 
                        stripos($groupName, 'Все пользователи') !== false || 
                        stripos($groupName, 'All users') !== false ||
                        stripos($groupName, 'неавторизованные') !== false) {
                        continue;
                    }
                    
                    // Группы календаря теперь тоже показываем в списке
                    
                    $groups[] = [
                        'ID' => $groupId,
                        'NAME' => $groupName,
                        'DESCRIPTION' => $group['DESCRIPTION'] ?? '',
                        'ACTIVE' => $group['ACTIVE'] ?? 'N'
                    ];
                }
            }
            
            return $groups;
        } catch (\Exception $e) {
            artmax_log('Ошибка получения групп Bitrix: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить привязанные группы Bitrix для группы календаря
     */
    public function getLinkedBitrixGroups($calendarGroupId)
    {
        // Проверяем существование таблицы
        if (!$this->tableExists('artmax_calendar_group_links')) {
            return [];
        }
        
        try {
            $sql = "
            SELECT gl.BITRIX_GROUP_ID, g.NAME, g.DESCRIPTION, g.ACTIVE
            FROM artmax_calendar_group_links gl
            INNER JOIN b_group g ON g.ID = gl.BITRIX_GROUP_ID
            WHERE gl.CALENDAR_GROUP_ID = " . (int)$calendarGroupId . "
            ORDER BY g.NAME ASC
            ";
            $result = $this->connection->query($sql);
            
            $groups = [];
            while ($row = $result->fetch()) {
                $groups[] = $row;
            }
            
            return $groups;
        } catch (\Exception $e) {
            error_log('Ошибка получения привязанных групп: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Привязать группу Bitrix к группе календаря
     */
    public function linkBitrixGroup($calendarGroupId, $bitrixGroupId)
    {
        // Проверяем существование таблицы
        if (!$this->tableExists('artmax_calendar_group_links')) {
            return [
                'success' => false,
                'error' => 'Таблица artmax_calendar_group_links не существует. Переустановите модуль.'
            ];
        }
        
        try {
            // Проверяем, не привязана ли уже эта группа
            $sqlCheck = "
            SELECT ID FROM artmax_calendar_group_links 
            WHERE CALENDAR_GROUP_ID = " . (int)$calendarGroupId . " 
            AND BITRIX_GROUP_ID = " . (int)$bitrixGroupId . "
            ";
            $result = $this->connection->query($sqlCheck);
            
            if ($result->getSelectedRowsCount() > 0) {
                return [
                    'success' => false,
                    'error' => 'Группа уже привязана'
                ];
            }
            
            // Привязываем группу
            $sqlInsert = "
            INSERT INTO artmax_calendar_group_links (CALENDAR_GROUP_ID, BITRIX_GROUP_ID) 
            VALUES (" . (int)$calendarGroupId . ", " . (int)$bitrixGroupId . ")
            ";
            $this->connection->query($sqlInsert);
            
            // Добавляем всех пользователей из привязанной группы в группу календаря
            $this->syncUsersFromLinkedGroup($calendarGroupId, $bitrixGroupId);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка привязки группы: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Отвязать группу Bitrix от группы календаря
     */
    public function unlinkBitrixGroup($calendarGroupId, $bitrixGroupId)
    {
        // Проверяем существование таблицы
        if (!$this->tableExists('artmax_calendar_group_links')) {
            return [
                'success' => false,
                'error' => 'Таблица artmax_calendar_group_links не существует. Переустановите модуль.'
            ];
        }
        
        try {
            $sqlDelete = "
            DELETE FROM artmax_calendar_group_links 
            WHERE CALENDAR_GROUP_ID = " . (int)$calendarGroupId . " 
            AND BITRIX_GROUP_ID = " . (int)$bitrixGroupId . "
            ";
            $this->connection->query($sqlDelete);
            
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Ошибка отвязки группы: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Синхронизировать пользователей из привязанной группы Bitrix в группу календаря
     */
    private function syncUsersFromLinkedGroup($calendarGroupId, $bitrixGroupId)
    {
        // Получаем всех пользователей из привязанной группы Bitrix
        $rsUsers = \CUser::GetList(
            $by = 'ID',
            $order = 'ASC',
            ['GROUPS_ID' => $bitrixGroupId]
        );
        
        while ($user = $rsUsers->Fetch()) {
            $userId = (int)$user['ID'];
            
            // Получаем текущие группы пользователя
            $userGroups = \CUser::GetUserGroup($userId);
            
            // Если пользователь еще не в группе календаря, добавляем его
            if (!in_array($calendarGroupId, $userGroups)) {
                $userGroups[] = $calendarGroupId;
                $userObj = new \CUser();
                $userObj->Update($userId, ['GROUP_ID' => $userGroups]);
            }
        }
    }

    /**
     * Получить пользователей группы календаря (включая пользователей из привязанных групп)
     */
    public function getGroupUsers($groupId)
    {
        $users = [];
        
        // Получаем пользователей напрямую из группы Bitrix
        // Используем более точный фильтр - проверяем, что группа действительно в списке групп пользователя
        $rsUsers = \CUser::GetList(
            $by = 'ID',
            $order = 'ASC',
            ['GROUPS_ID' => $groupId]
        );
        
        // Получаем список привязанных групп ДО цикла, чтобы не вызывать метод несколько раз
        $linkedGroups = $this->getLinkedBitrixGroups($groupId);
        $hasLinkedGroups = !empty($linkedGroups);
        
        while ($user = $rsUsers->Fetch()) {
            $userId = (int)$user['ID'];
            
            // Дополнительная проверка: убеждаемся, что пользователь действительно состоит в этой группе
            $userGroups = \CUser::GetUserGroup($userId);
            
            if (in_array($groupId, $userGroups)) {
                // Если нет привязанных групп, проверяем, не является ли пользователь администратором
                // Администраторы могут автоматически попадать во все группы, но мы не должны их показывать,
                // если группа только что создана и в нее никто не был явно добавлен
                if (!$hasLinkedGroups) {
                    // Проверяем, является ли пользователь администратором системы
                    $isAdmin = false;
                    try {
                        // Группа администраторов Bitrix обычно имеет ID = 1
                        $adminGroupId = 1;
                        if (in_array($adminGroupId, $userGroups)) {
                            $isAdmin = true;
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки при проверке
                    }
                    
                    // Если пользователь - администратор и нет привязанных групп, пропускаем его
                    // (так как он не был явно добавлен в группу календаря)
                    if ($isAdmin) {
                        continue;
                    }
                }
                
                $users[$userId] = [
                    'ID' => $userId,
                    'NAME' => $user['NAME'],
                    'LAST_NAME' => $user['LAST_NAME'],
                    'LOGIN' => $user['LOGIN'],
                    'EMAIL' => $user['EMAIL'],
                    'SOURCE' => 'direct' // Прямое добавление в группу
                ];
            }
        }
        
        // Получаем пользователей из привязанных групп Bitrix
        try {
            // $linkedGroups уже получены выше
            foreach ($linkedGroups as $linkedGroup) {
                $rsLinkedUsers = \CUser::GetList(
                    $by = 'ID',
                    $order = 'ASC',
                    ['GROUPS_ID' => $linkedGroup['BITRIX_GROUP_ID']]
                );
                
                while ($user = $rsLinkedUsers->Fetch()) {
                    $userId = (int)$user['ID'];
                    
                    // Дополнительная проверка: убеждаемся, что пользователь действительно состоит в привязанной группе
                    $userGroups = \CUser::GetUserGroup($userId);
                    if (in_array($linkedGroup['BITRIX_GROUP_ID'], $userGroups)) {
                        // Добавляем только если пользователь еще не добавлен
                        if (!isset($users[$userId])) {
                            $users[$userId] = [
                                'ID' => $userId,
                                'NAME' => $user['NAME'],
                                'LAST_NAME' => $user['LAST_NAME'],
                                'LOGIN' => $user['LOGIN'],
                                'EMAIL' => $user['EMAIL'],
                                'SOURCE' => 'linked', // Из привязанной группы
                                'LINKED_GROUP_NAME' => $linkedGroup['NAME']
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Если ошибка при получении привязанных групп - просто возвращаем пользователей из основной группы
            error_log('Ошибка получения пользователей из привязанных групп: ' . $e->getMessage());
        }
        
        return array_values($users);
    }

    /**
     * Проверить существование таблицы в базе данных
     */
    private function tableExists($tableName)
    {
        try {
            $sql = "SHOW TABLES LIKE '" . $this->sqlHelper->forSql($tableName) . "'";
            $result = $this->connection->query($sql);
            return $result->getSelectedRowsCount() > 0;
        } catch (\Exception $e) {
            error_log('Ошибка проверки существования таблицы ' . $tableName . ': ' . $e->getMessage());
            return false;
        }
    }
}
