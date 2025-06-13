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
	<ul class="nav nav-tabs" id="bbb_list" role="tablist">
		<li class="nav-item active">
			<a class="nav-link" id="id-tab-question" data-toggle="tab" href="#id-content-question" role="tab" aria-controls="id-content-question" aria-selected="true">{l s='Questions' mod='quizz'}</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="id-tab-choice" data-toggle="tab" href="#id-content-choice" role="tab" aria-controls="id-content-choice" aria-selected="false">{l s='Choix / Réponses possibles' mod='quizz'}</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="id-tab-nicotine" data-toggle="tab" href="#id-content-nicotine" role="tab" aria-controls="id-content-nicotine" aria-selected="false">{l s='Nicotine' mod='quizz'}</a>
		</li>
		<li class="nav-item">
			<a class="nav-link" id="id-tab-result" data-toggle="tab" href="#id-content-result" role="tab" aria-controls="id-content-result" aria-selected="false">{l s='Résultats' mod='quizz'}</a>
		</li>
	</ul>
	<div class="tab-content" id="bbb">
		<div class="tab-pane fade active in" id="id-content-question" role="tabpanel" aria-labelledby="id-content-question-tab">
		{$question}		
		</div>
		<div class="tab-pane fade" id="id-content-choice" role="tabpanel" aria-labelledby="id-content-choice-tab">
		{$choice}
		</div>
		<div class="tab-pane fade" id="id-content-nicotine" role="tabpanel" aria-labelledby="id-content-nicotine-tab">
		{$nicotine}
		</div>
		<div class="tab-pane fade" id="id-content-result" role="tabpanel" aria-labelledby="id-content-result-tab">
		{$result}
		</div>		
	</div>
</div>

