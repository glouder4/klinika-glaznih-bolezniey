import {Type} from 'main.core';

export class AddMenuItem
{
	constructor(options = {name: 'AddMenuItem'})
	{
		console.log(options)
		this.name = options.name;
		this.menuId = options.menuId || 'custom_menu_item';
		this.menuItemText = options.menuItemText || 'Новый пункт';
		this.menuItemUrl = options.menuItemUrl || '#';
		this.menuItemClass = options.menuItemClass || '';
		this.menuItemIcon = options.menuItemIcon || 'icon_name';
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
			
			// Добавляем пункт в меню
			_this.addMenuItem();
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

	addMenuItem()
	{
		// BX.Main.menuManager.add({
		// 	id: this.menuId,
		// 	text: this.menuItemText,
		// 	title: this.menuItemTitle,
		// 	url: this.menuItemUrl,
		// 	icon: this.menuItemIcon,
		// 	className: this.menuItemClass
		// });
		
		console.log('Menu item added:', this.menuItemText);
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

// Пример использования
const addMenuItem = new AddMenuItem({
	name: 'CalendarMenuItem',
	menuItemText: 'Календарь',
	menuItemUrl: '/calendar/',
	menuItemClass: 'calendar-menu-item',
	menuItemIcon: 'calendar-icon',
	menuItemTitle: 'Управление календарем',
	sidePanelEnabled: true
});
