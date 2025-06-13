
<div class="general_quizz">	
{if isset($result_quizz)}
	{*$result_quizz|print_r*}
	<div class="results">		
		{foreach $result_quizz as $result}
			{if isset($result.id_question)}
				{if $result.id_question==1}<h3 class="recap">{l s='Récapitulatif' d='Shop.Theme.Custom'}</h3>{/if}
				<div class="inlineblock col-xs-12 col-lg-6 col-xl-6 question answer" id="main_{$result.id_question}">
					<h2><span>{$result.id_question} / </span>{$result.content nofilter}</h2>
					{if $result.type=="numeric"}
						<div class="quantity choices" id="{$result.id_question}_numeric">
							{foreach $result.choix as $choice}
								<div id="question_{$choice.id_choice}" class="result question_{$choice.id_choice} numeric">{$choice.answer}</div>
							{/foreach}
						</div>
					{elseif $result.type=="decimal"}
						<div class="quantity choices" id="{$result.id_question}_numeric">
							{foreach $result.choix as $choice}
								<div id="question_{$choice.id_choice}" class="result question_{$choice.id_choice} numeric">{$choice.prix} €</div>
							{/foreach}
						</div>
					{elseif $result.type=="radio"}
						<div class="choices" id="question_{$result.id_question}">
						{foreach $result.choix as $choice}
							<div class="choice"><div id="choice_{$choice.id_choice}" class="result question_{$result.id_question} radio">{$choice.answer}</div></div>
						{/foreach}
						</div>
					{elseif $result.type=="select"}
						<div class="choices" id="question_{$result.id_question}">				
							{foreach $result.choix as $choice}
								<div id="choice_{$choice.id_choice}" class="result choice">{$choice.answer}</div>
							{/foreach}
						</div>
					{/if}
				</div>
			{else}
			<div class="inlineblock col-xs-12 col-lg-12 col-xl-12 question answer section_{$result.section} type_result_{$result.niveau} result_sections" id="section_{$result.section}">
				{if isset($result.image)}<span><img src="{$urls.img_url}section_{$result.section}.png" alt="RESULT HAPPESMOKE" /></span>{/if}
				<div>
					<h2>{$result.name_section nofilter}{* : {$result.points} / {$result.max_points} => *} {$result.niveau nofilter}</h2>
					{if !isset($result.taux)}
					<div class="flex-wrapper">
						<div class="single-chart">
							<svg viewBox="0 0 36 36" class="circular-chart orange">
							  <path class="circle-bg"
								d="M18 2.0845
								  a 15.9155 15.9155 0 0 1 0 31.831
								  a 15.9155 15.9155 0 0 1 0 -31.831"
							  />
							  <path class="circle"
								stroke-dasharray="{$result.percent}, 100"
								d="M18 2.0845
								  a 15.9155 15.9155 0 0 1 0 31.831
								  a 15.9155 15.9155 0 0 1 0 -31.831"
							  />
							  <text x="18" y="21" class="percentage">{$result.percent}%</text>
							</svg>
						</div>
					</div>
					<p>{$result.description nofilter}</p>
					{else}
					<div class="flex_center_width">
						<div class="nicotine">
							<p>{l s='taux de nicotine minimum conseillé' d='Shop.Theme.Custom'}</p>
							<span>{$result.taux|replace:"mg":""}</span>
							<p>{l s='mg / ml' d='Shop.Theme.Custom'}</p>
						</div>
						<div class="description"><p>{$result.nicotinedesc nofilter}</p></div>
						<div class="economies">
							<p>{l s='Vous économiserez' d='Shop.Theme.Custom'}</p>
							<span>{$result.eco}</span>
							<p>{l s='par an' d='Shop.Theme.Custom'}</p>
						</div>
					</div>
					{/if}
				</div>
			</div>
			{/if}
		{/foreach}
	</div>
{elseif isset($questions)}
	<form action="" method="POST">
	{foreach $questions as $question}
		<div class="inlineblock col-xs-12 col-lg-6 col-xl-6 question" id="main_{$question.id_question}">
			<h2><span>{$question.id_question} / </span>{$question.content nofilter}</h2>
			{if $question.type=="numeric"}
				<div class="quantity choices" id="{$question.id_question}_numeric">
					<input type="number" name="question_{$question.id_question}" id="question_{$question.id_question}" class="question_{$question.id_question} numeric"  step="1" min="0" max="50" value="0" />
				</div>
			{elseif $question.type=="decimal"}
				<div class="quantity choices" id="{$question.id_question}_numeric">
					<input type="number" name="question_{$question.id_question}" id="question_{$question.id_question}" class="question_{$question.id_question} decimal"  step="0.01" min="0" max="50" value="0" />
				</div>
			{elseif $question.type=="radio"}
				<div class="choices">
				{foreach $question.choix as $choice}
				<div class="choice"><input type="radio" name="question_{$question.id_question}" value="{$choice.id_choice}" id="choice_{$choice.id_choice}" class="question_{$question.id_question} radio" /> {$choice.content}</div>
				{/foreach}
				</div>
			{elseif $question.type=="select"}
				<div class="choices">
					<select name="question_{$question.id_question}" id="question_{$question.id_question}" class="select">
					{foreach $question.choix as $choice}
						<option id="choice_{$choice.id_choice}" value="{$choice.id_choice}" />{$choice.content}</option>
					{/foreach}
					</select>
				</div>
			{/if}
		</div>
	{/foreach}
		<input type="hidden" name="results" value="1" />
		<input type="submit" value="Calculer" />
	</form>
{/if}
</div>