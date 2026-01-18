-- SQL миграция для добавления новых прав в модуль artmax.calendar
-- Дата создания: 2026-01-XX
-- Описание: Добавляет новые права доступа и удаляет устаревшее право calendar.admin

-- ============================================
-- 1. Удаление устаревших прав
-- ============================================
-- Удаление права calendar.admin
-- Удаляем связи с группами/пользователями (каскадное удаление через FOREIGN KEY)
DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.admin'
);

-- Удаляем само право
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.admin';

-- Удаление права calendar.view (не используется, заменено на calendar.view_others)
-- Удаляем связи с группами/пользователями
DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.view'
);

-- Удаляем само право
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.view';

-- ============================================
-- 2. Добавление новых прав (с проверкой на существование)
-- ============================================

-- calendar.edit - Редактирование записи
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.edit', 'Редактирование записи', 'Право на редактирование существующих записей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit'
);

-- calendar.delete - Удаление записи
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.delete', 'Удаление записи', 'Право на удаление записей из календаря'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.delete'
);

-- calendar.move - Перемещение записи
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.move', 'Перемещение записи', 'Право на перемещение записей в календаре'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.move'
);

-- calendar.confirm - Подтверждение записи
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.confirm', 'Подтверждение записи', 'Право на подтверждение записей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.confirm'
);

-- calendar.change_employee - Смена ответственного врача
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.change_employee', 'Смена ответственного врача', 'Право на смену ответственного врача в записи'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.change_employee'
);

-- calendar.edit_title - Редактирование названия записи
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.edit_title', 'Редактирование названия записи', 'Право на редактирование названия записей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_title'
);

-- calendar.edit_others_notes - Редактирование заметок чужих записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.edit_others_notes', 'Редактирование заметок чужих записей', 'Право на редактирование заметок в чужих записях'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_others_notes'
);

-- calendar.manage_schedule - Управление расписанием
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.manage_schedule', 'Управление расписанием', 'Право на управление расписанием врачей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.manage_schedule'
);

-- ============================================
-- 3. Проверка существующих прав (для справки)
-- ============================================
-- Следующие права должны уже существовать в системе:
-- - calendar.view
-- - calendar.view_others
-- - calendar.create
-- - calendar.manage_branches
-- - calendar.manage_employees
-- - calendar.manage_groups

-- ============================================
-- 4. Примечания
-- ============================================
-- После выполнения миграции необходимо:
-- 1. Назначить новые права группам пользователей через админ-панель
--    или выполнить скрипт назначения прав по умолчанию
-- 2. Проверить, что все права корректно отображаются в интерфейсе
-- 3. Убедиться, что проверки прав работают корректно
