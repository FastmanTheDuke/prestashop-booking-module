{* 
 * 08/2106
 * beforebigbang/La Teapot du web pour le vieux plongeur
 * Import version 32 : on a désormais la possibilité de créer des produits;
 * ceci associe aussi aux boutiques les produits
 *
  *}


<div class="panel" id="import-process" style="display:none">
    <input type="hidden" name="import_controller_link" id ="import_controller_link" value="{$import_controller_link}" />
    <div class="panel-heading"><i class="icon-spinner"></i> Traitement en cours</div>
    <button class="btn btn-default" id="import-process-stop"><i class="icon-gavel"></i> Arreter </button>
    <textarea id="import-process-result" style="width:100%;height:400px;overflow: scroll;"></textarea>
</div>