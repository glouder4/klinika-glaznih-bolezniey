-- SQL миграция для рефакторинга системы прав доступа
-- Дата: 2026-01-18
-- Описание: Упрощение системы прав - удалены устаревшие права, добавлены новые с понятной структурой

-- ============================================
-- 1. Удаление устаревших прав
-- ============================================

-- Удаляем связи с группами/пользователями
DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.view_others'
);

DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit'
);

DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_title'
);

DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_others_notes'
);

DELETE FROM artmax_calendar_access_rights 
WHERE PERMISSION_ID IN (
    SELECT ID FROM artmax_calendar_permissions WHERE CODE = 'calendar.delete'
);

-- Удаляем сами права
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.view_others';
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit';
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_title';
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_others_notes';
DELETE FROM artmax_calendar_permissions WHERE CODE = 'calendar.delete';

-- ============================================
-- 2. Добавление новых прав (с проверкой на существование)
-- ============================================

-- calendar.view - Просмотр записей (своих)
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.view', 'Просмотр записей', 'Право на просмотр своих записей в календаре'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.view'
);

-- calendar.view_all - Просмотр всех записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.view_all', 'Просмотр всех записей', 'Право на просмотр записей всех врачей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.view_all'
);

-- calendar.create - Создание записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.create', 'Создание записи', 'Право на создание новых записей в календаре'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.create'
);

-- calendar.edit_own - Редактирование своих записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.edit_own', 'Редактирование своих записей', 'Право на редактирование только своих записей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_own'
);

-- calendar.edit_all - Редактирование всех записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.edit_all', 'Редактирование всех записей', 'Право на редактирование записей всех врачей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.edit_all'
);

-- calendar.move - Перемещение записей
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

-- calendar.delete_own - Удаление своих записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.delete_own', 'Удаление своих записей', 'Право на удаление только своих записей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.delete_own'
);

-- calendar.delete_all - Удаление всех записей
INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION)
SELECT 'calendar.delete_all', 'Удаление всех записей', 'Право на удаление записей всех врачей'
WHERE NOT EXISTS (
    SELECT 1 FROM artmax_calendar_permissions WHERE CODE = 'calendar.delete_all'
);

-- ============================================
-- 3. Проверка результата
-- ============================================

-- SELECT CODE, NAME FROM artmax_calendar_permissions ORDER BY CODE;