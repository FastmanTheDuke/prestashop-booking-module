{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<div class="panel">
	<h3 class="card-header">
		<i class="material-icons">settings</i> {l s='DEALER LOCATOR' d='Shop.Theme.Custom'}
	</h3>
	
	<h3><i class="material-icons mi-settings_applications"></i> {l s='Toutes les traductions sont dans Shop -> Theme -> Custom' d='Shop.Theme.Custom'}</h3>
	<p><a href="{$import_controller_link}">{l s='Importer boutiques/revendeurs' d='Shop.Theme.Custom'}</a></p>
	{if $module_link}<p><a href="{$module_link}" target="_blank">{l s='Voir la page MODULE LOCATOR' d='Shop.Theme.Custom'}</a></p>{/if}
	{if $cms_id}<p><a href="{$cms_link}" target="_blank">{l s='Voir la page CMS LOCATOR' d='Shop.Theme.Custom'} - ID {$cms_id}</a></p>{/if}
	{if $category_cms_id}<p>{l s='Catégorie des pages CMS LOCATOR' d='Shop.Theme.Custom'} - ID {$category_cms_id}</p>{/if}
	
</div>

