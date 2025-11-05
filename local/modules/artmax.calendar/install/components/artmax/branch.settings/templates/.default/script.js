/**
 * ArtMax Branch Settings Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeBranchSettingsForm() {
    // Переменные для мультиселектора
    let selectedEmployees = [];
    let allEmployees = [];
    let branchId = window.branchSettingsData ? window.branchSettingsData.branchId : null;
    
    // Функция закрытия SidePanel
    window.closeSidePanel = function() {
        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        } else {
            // Fallback для старых версий или если SidePanel не доступен
            if (window.parent) {
                window.parent.postMessage({
                    type: 'calendar:closePanel'
                }, '*');
            }
            window.close();
        }
    };

    // Функция получения CSRF токена
    function getCSRFToken() {
        const tokenInput = document.querySelector('input[name="sessid"]');
        return tokenInput ? tokenInput.value : '';
    }

    // Загрузка всех сотрудников
    function loadEmployees() {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                allEmployees = data.employees;
                renderEmployeeOptions(allEmployees);
                
                // После загрузки всех сотрудников загружаем выбранных сотрудников филиала
                if (branchId) {
                    loadBranchEmployees(branchId);
                }
            } else {
                console.error('Ошибка загрузки сотрудников:', data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников:', error);
        });
    }

    // Загрузка выбранных сотрудников филиала
    function loadBranchEmployees(branchId) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getBranchEmployees',
                branch_id: branchId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                // Очищаем текущий список выбранных сотрудников
                selectedEmployees = [];
                
                // Добавляем сотрудников филиала в выбранные
                data.employees.forEach(employee => {
                    selectedEmployees.push(employee);
                });
                
                // Обновляем отображение
                updateSelectedEmployeesDisplay();
                
                // Обновляем чекбоксы в выпадающем списке
                setTimeout(() => {
                    updateEmployeeCheckboxes();
                }, 100);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников филиала:', error);
        });
    }

    // Поиск сотрудников
    function searchEmployees(query) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'searchEmployees',
                query: query,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                renderEmployeeOptions(data.employees);
            } else {
                console.error('Ошибка поиска сотрудников:', data.error);
                // В случае ошибки показываем всех сотрудников
                renderEmployeeOptions(allEmployees);
            }
        })
        .catch(error => {
            console.error('Ошибка при поиске сотрудников:', error);
            // В случае ошибки показываем всех сотрудников
            renderEmployeeOptions(allEmployees);
        });
    }

    // Отображение опций сотрудников
    function renderEmployeeOptions(employees) {
        const optionsContainer = document.getElementById('multiselect-options');
        if (!optionsContainer) return;

        optionsContainer.innerHTML = '';
        
        employees.forEach(employee => {
            const option = document.createElement('div');
            option.className = 'multiselect-option';
            const isSelected = selectedEmployees.some(emp => String(emp.ID) === String(employee.ID));
            if (isSelected) {
                option.classList.add('selected');
            }
            
            option.innerHTML = `
                <input type="checkbox" id="emp-${employee.ID}" value="${employee.ID}" 
                       ${isSelected ? 'checked' : ''}>
                <label for="emp-${employee.ID}">${employee.NAME} ${employee.LAST_NAME}</label>
            `;
            
            option.addEventListener('click', (e) => {
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    const checkbox = option.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                    toggleEmployee(employee, checkbox.checked);
                }
            });
            
            const checkbox = option.querySelector('input[type="checkbox"]');
            checkbox.addEventListener('change', (e) => {
                toggleEmployee(employee, e.target.checked);
            });
            
            optionsContainer.appendChild(option);
        });
    }

    // Переключение сотрудника (добавить/удалить)
    function toggleEmployee(employee, isSelected) {
        if (isSelected) {
            if (!selectedEmployees.some(emp => String(emp.ID) === String(employee.ID))) {
                selectedEmployees.push(employee);
            }
        } else {
            selectedEmployees = selectedEmployees.filter(emp => String(emp.ID) !== String(employee.ID));
        }
        updateSelectedEmployeesDisplay();
    }

    // Обновление отображения выбранных сотрудников
    function updateSelectedEmployeesDisplay() {
        const container = document.getElementById('selected-employees');
        const input = document.getElementById('multiselect-input');
        const placeholder = input ? input.querySelector('.placeholder') : null;
        
        if (!container || !placeholder) {
            return;
        }

        // Очищаем контейнер
        container.innerHTML = '';
        
        if (selectedEmployees.length > 0) {
            placeholder.textContent = `Выбрано: ${selectedEmployees.length}`;
            
            selectedEmployees.forEach(employee => {
                const tag = document.createElement('div');
                tag.className = 'selected-employee';
                tag.innerHTML = `
                    ${employee.NAME} ${employee.LAST_NAME}
                    <button type="button" class="remove-employee" onclick="removeEmployee(${employee.ID})">×</button>
                `;
                container.appendChild(tag);
            });
        } else {
            placeholder.textContent = 'Выберите сотрудников';
        }
    }

    // Удаление сотрудника из выбранных
    window.removeEmployee = function(employeeId) {
        const stringEmployeeId = String(employeeId);
        selectedEmployees = selectedEmployees.filter(emp => String(emp.ID) !== stringEmployeeId);
        updateSelectedEmployeesDisplay();
        
        // Обновляем чекбокс в выпадающем списке
        const checkbox = document.getElementById(`emp-${employeeId}`);
        if (checkbox) {
            checkbox.checked = false;
        }
        
        // Обновляем класс selected на опции
        const option = checkbox ? checkbox.closest('.multiselect-option') : null;
        if (option) {
            option.classList.remove('selected');
        }
    };

    // Обновление чекбоксов сотрудников
    function updateEmployeeCheckboxes() {
        const checkboxes = document.querySelectorAll('.multiselect-option input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            const employeeId = String(checkbox.value);
            const isSelected = selectedEmployees.some(emp => String(emp.ID) === String(employeeId));
            checkbox.checked = isSelected;
            
            // Обновляем класс selected
            const option = checkbox.closest('.multiselect-option');
            if (option) {
                if (isSelected) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            }
        });
    }

    // Инициализация мультиселектора
    function initMultiselect() {
        const input = document.getElementById('multiselect-input');
        const dropdown = document.getElementById('multiselect-dropdown');
        const searchInput = document.getElementById('employee-search');
        
        if (!input || !dropdown) return;

        // Открытие/закрытие dropdown
        input.addEventListener('click', () => {
            const isActive = input.classList.contains('active');
            if (isActive) {
                input.classList.remove('active');
                dropdown.classList.remove('show');
                setTimeout(() => {
                    dropdown.style.display = 'none';
                }, 300);
            } else {
                input.classList.add('active');
                dropdown.style.display = 'block';
                setTimeout(() => {
                    dropdown.classList.add('show');
                }, 10);
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        // Поиск сотрудников
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                if (!query) {
                    renderEmployeeOptions(allEmployees);
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchEmployees(query);
                }, 300);
            });
        }

        // Закрытие при клике вне элемента
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                input.classList.remove('active');
                dropdown.classList.remove('show');
                setTimeout(() => {
                    dropdown.style.display = 'none';
                }, 300);
            }
        });
    }

    // Функция сохранения настроек филиала
    window.saveBranchSettings = function() {
        const form = document.getElementById('branch-settings-form');
        const formData = new FormData(form);
        
        // Добавляем action для AJAX обработчика
        formData.append('action', 'saveBranchSettings');
        
        // Добавляем выбранных сотрудников
        const employeeIds = selectedEmployees.map(emp => emp.ID);
        formData.append('employee_ids', JSON.stringify(employeeIds));

        const csrfToken = getCSRFToken();
        
        // Показываем индикатор загрузки
        const saveBtn = document.getElementById('save-branch-settings-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.value = 'Сохранение...';
        }
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Показываем уведомление об успехе
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Настройки филиала сохранены',
                        position: 'top-right'
                    });
                }
                
                // Отправляем сообщение родительскому окну
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:branchSettingsSaved',
                        branchId: branchId
                    }, '*');
                }
                
                // Закрываем SidePanel
                setTimeout(() => {
                    closeSidePanel();
                }, 500);
            } else {
                // Показываем уведомление об ошибке
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'),
                        position: 'top-right'
                    });
                } else {
                    alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
                }
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении настроек:', error);
            // Показываем уведомление об ошибке
            if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                BX.UI.Notification.Center.notify({
                    content: 'Ошибка соединения с сервером',
                    position: 'top-right'
                });
            } else {
                alert('Ошибка соединения с сервером');
            }
        })
        .finally(() => {
            // Восстанавливаем кнопку
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.value = 'Сохранить';
            }
        });
    };

    // Инициализация формы
    loadEmployees();
    initMultiselect();
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeBranchSettingsForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBranchSettingsForm);
    } else {
        initializeBranchSettingsForm();
    }
}

