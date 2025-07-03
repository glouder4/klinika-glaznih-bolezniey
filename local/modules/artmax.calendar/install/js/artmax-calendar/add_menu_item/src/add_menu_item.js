import {Type} from 'main.core';

export class AddMenuItem
{
	constructor(options = {name: 'AddMenuItem'})
	{
		console.log(options)
		this.name = options.name;
		this.menuId = options.menuId || 'artmax_calendar_menu';
		this.menuItemText = options.menuItemText || 'Календарь ArtMax';
		this.menuItemUrl = options.menuItemUrl || '/artmax-calendar/1';
		this.menuItemClass = options.menuItemClass || 'artmax-calendar-menu-item';
		this.menuItemIcon = options.menuItemIcon || 'calendar-icon';
		this.menuItemTitle = options.menuItemTitle || this.menuItemText;
		this.sidePanelEnabled = options.sidePanelEnabled !== false;

		this.init();
	}

	init()
	{
		let _this = this;
		BX.ready(function() {
			// Настройка SidePanel если включено
			if (_this.sidePanelEnabled) {
				_this.setupSidePanel();
			}
			
			// Добавляем пункт в меню профиля пользователя
			_this.addToUserProfileMenu();
		});
	}

	setupSidePanel()
	{
		BX.SidePanel.Instance.bindAnchors({
			rules: [
				{
					condition: [this.menuItemUrl],
					options: {
						cacheable: false,
						title: this.menuItemTitle,
						icon: this.menuItemIcon
					}
				}
			]
		});
	}

	addToUserProfileMenu()
	{
		// Ждем инициализации меню профиля пользователя
		BX.addCustomEvent("BX.UI.EntityConfigurationManager:onInitialize", (editor, settings) => {
			if (editor.getId() != "intranet-user-profile") {
				return;
			}

			const entityId = editor._entityId;
			const menuId = "#socialnetwork_profile_menu_user_" + entityId;
			const menuNode = document.querySelector(menuId);

			if (!BX.type.isDomNode(menuNode)) {
				console.warn('Menu node not found:', menuId);
				return;
			}

			console.log('Adding calendar menu item to profile menu');
			this.createAndAddMenuItem(menuNode);
		});
	}

	createAndAddMenuItem(menuContainer)
	{
		// Проверяем, не добавлен ли уже пункт меню
		if (menuContainer.querySelector('.' + this.menuItemClass)) {
			return;
		}

		const menuItem = document.createElement('li');
		menuItem.className = 'socialnetwork-profile-menu-item ' + this.menuItemClass;
		
		const menuLink = document.createElement('a');
		menuLink.href = this.menuItemUrl;
		menuLink.textContent = this.menuItemText;
		menuLink.title = this.menuItemTitle;
		menuLink.className = 'socialnetwork-profile-menu-item-link';
		
		// Добавляем иконку если есть
		if (this.menuItemIcon) {
			const icon = document.createElement('i');
			icon.className = this.menuItemIcon;
			icon.style.marginRight = '5px';
			menuLink.insertBefore(icon, menuLink.firstChild);
		}
		
		menuItem.appendChild(menuLink);
		menuContainer.appendChild(menuItem);
		
		console.log('Calendar menu item added successfully:', this.menuItemText);
	}

	setName(name)
	{
		if (Type.isString(name))
		{
			this.name = name;
		}
	}

	getName()
	{
		return this.name;
	}

	setMenuItemText(text)
	{
		if (Type.isString(text))
		{
			this.menuItemText = text;
		}
	}

	setMenuItemUrl(url)
	{
		if (Type.isString(url))
		{
			this.menuItemUrl = url;
		}
	}

	setMenuItemIcon(icon)
	{
		if (Type.isString(icon))
		{
			this.menuItemIcon = icon;
		}
	}

	setMenuItemTitle(title)
	{
		if (Type.isString(title))
		{
			this.menuItemTitle = title;
		}
	}

	setSidePanelEnabled(enabled)
	{
		this.sidePanelEnabled = !!enabled;
	}
}

// Автоматическая инициализация при загрузке
const addMenuItem = new AddMenuItem({
	name: 'CalendarMenuItem',
	menuItemText: 'Календарь ArtMax',
	menuItemUrl: '/artmax-calendar/1',
	menuItemClass: 'artmax-calendar-menu-item',
	menuItemIcon: 'calendar-icon',
	menuItemTitle: 'Управление календарем событий',
	sidePanelEnabled: true
});
