{*
* Template pour la vue calendrier des disponibilités
* Gestion des créneaux de disponibilité avec outils avancés
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar-o"></i>
        Calendrier des Disponibilités
        <span class="badge badge-success">
            {$stats.future_availabilities} futures disponibilités
        </span>
    </div>
</div>

{* Statistiques rapides *}
<div class="row">
    <div class="col-lg-3 col-md-6">
        <div class="alert alert-info text-center">
            <div style="font-size: 2em; font-weight: bold;">{$stats.total_availabilities}</div>
            <div>Total disponibilités</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="alert alert-success text-center">
            <div style="font-size: 2em; font-weight: bold;">{$stats.active_availabilities}</div>
            <div>Actives</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="alert alert-primary text-center">
            <div style="font-size: 2em; font-weight: bold;">{$stats.future_availabilities}</div>
            <div>Futures</div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="alert alert-warning text-center">
            <div style="font-size: 1.5em; font-weight: bold;">{$stats.occupancy_rate}%</div>
            <div>Taux d'occupation</div>
        </div>
    </div>
</div>

{* Barre d'outils avancés *}
<div class="panel">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-primary" id="today-btn">
                        <i class="icon-home"></i> Aujourd'hui
                    </button>
                    <button type="button" class="btn btn-default" id="prev-btn">
                        <i class="icon-chevron-left"></i> Précédent
                    </button>
                    <button type="button" class="btn btn-default" id="next-btn">
                        Suivant <i class="icon-chevron-right"></i>
                    </button>
                </div>
                
                <div class="btn-group ml-2" role="group">
                    <button type="button" class="btn btn-info" data-view="dayGridMonth">Mois</button>
                    <button type="button" class="btn btn-default" data-view="timeGridWeek">Semaine</button>
                </div>
            </div>
            
            <div class="col-md-6 text-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-success" id="new-availability-btn">
                        <i class="icon-plus"></i> Nouvelle disponibilité
                    </button>
                    <button type="button" class="btn btn-info" id="bulk-create-btn">
                        <i class="icon-calendar"></i> Création en lot
                    </button>
                    <button type="button" class="btn btn-warning" id="copy-week-btn">
                        <i class="icon-copy"></i> Copier semaine
                    </button>
                </div>
            </div>
        </div>
        
        <hr>
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="booker-filter">Filtrer par élément :</label>
                    <select class="form-control" id="booker-filter">
                        <option value="all">Tous les éléments</option>
                        {foreach from=$bookers item=booker}
                            <option value="{$booker.id_booker}">
                                {$booker.name|escape:'html':'UTF-8'}
                                {if $booker.price} - {$booker.price}€{/if}
                            </option>
                        {/foreach}
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="preset-times">Créneaux prédéfinis :</label>
                    <select class="form-control" id="preset-times">
                        {foreach from=$preset_times key=time_key item=time_label}
                            <option value="{$time_key}">{$time_label|escape:'html':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

{* Mode sélection multiple *}
<div class="panel panel-default" id="selection-mode-panel" style="display: none;">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-8">
                <i class="icon-hand-pointer-o"></i>
                <strong>Mode sélection activé</strong> - Cliquez sur les dates pour sélectionner
                <span id="selected-dates-count" class="badge badge-primary ml-2">0</span> date(s) sélectionnée(s)
            </div>
            <div class="col-md-4 text-right">
                <button type="button" class="btn btn-success btn-sm" id="apply-bulk-create">
                    <i class="icon-check"></i> Créer disponibilités
                </button>
                <button type="button" class="btn btn-default btn-sm" id="cancel-selection">
                    <i class="icon-times"></i> Annuler
                </button>
            </div>
        </div>
    </div>
</div>

{* Calendrier principal *}
<div class="panel">
    <div class="panel-body">
        <div id="calendar"></div>
    </div>
</div>

{* Légende *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-info"></i> Légende
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-4">
                <span class="label" style="background-color: #28a745;">■</span>
                Disponible (sans réservation)
            </div>
            <div class="col-md-4">
                <span class="label" style="background-color: #ffc107; color: #212529;">■</span>
                Disponible (avec réservations)
            </div>
            <div class="col-md-4">
                <span class="label" style="background-color: #dc3545;">■</span>
                Non disponible
            </div>
        </div>
    </div>
</div>

{* Modal pour création/édition de disponibilité *}
<div class="modal fade" id="availability-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title" id="availability-modal-title">Nouvelle disponibilité</h4>
            </div>
            
            <div class="modal-body">
                <form id="availability-form">
                    <input type="hidden" id="availability-id" name="availability_id">
                    
                    <div class="form-group">
                        <label for="modal-booker">Élément *</label>
                        <select class="form-control" id="modal-booker" name="booker_id" required>
                            <option value="">Sélectionner un élément</option>
                            {foreach from=$bookers item=booker}
                                <option value="{$booker.id_booker}">
                                    {$booker.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal-date-from">Date de début *</label>
                                <input type="date" class="form-control" id="modal-date-from" name="date_from" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal-date-to">Date de fin *</label>
                                <input type="date" class="form-control" id="modal-date-to" name="date_to" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="icon-lightbulb-o"></i>
                        <strong>Astuce :</strong> Pour une disponibilité sur une seule journée, utilisez la même date pour le début et la fin.
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="delete-availability-btn" style="display: none;">
                    <i class="icon-trash"></i> Supprimer
                </button>
                <button type="button" class="btn btn-primary" id="save-availability-btn">
                    <i class="icon-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal pour création en lot *}
<div class="modal fade" id="bulk-create-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">Création en lot de disponibilités</h4>
            </div>
            
            <div class="modal-body">
                <form id="bulk-create-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-booker">Élément *</label>
                                <select class="form-control" id="bulk-booker" name="booker_id" required>
                                    <option value="">Sélectionner un élément</option>
                                    {foreach from=$bookers item=booker}
                                        <option value="{$booker.id_booker}">
                                            {$booker.name|escape:'html':'UTF-8'}
                                        </option>
                                    {/foreach}
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="bulk-start-date">Date de début *</label>
                                <input type="date" class="form-control" id="bulk-start-date" name="start_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bulk-duration">Durée *</label>
                                <select class="form-control" id="bulk-duration" name="duration_weeks" required>
                                    <option value="1">1 semaine</option>
                                    <option value="2">2 semaines</option>
                                    <option value="4" selected>4 semaines (1 mois)</option>
                                    <option value="8">8 semaines (2 mois)</option>
                                    <option value="12">12 semaines (3 mois)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jours de la semaine *</label>
                                <div class="checkbox-list">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="1"> Lundi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="2"> Mardi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="3"> Mercredi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="4"> Jeudi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="5"> Vendredi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="6"> Samedi
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="selected_days[]" value="0"> Dimanche
                                    </label>
                                </div>
                                
                                <div class="mt-2">
                                    <button type="button" class="btn btn-xs btn-default" id="select-weekdays">
                                        Lun-Ven
                                    </button>
                                    <button type="button" class="btn btn-xs btn-default" id="select-weekend">
                                        Sam-Dim
                                    </button>
                                    <button type="button" class="btn btn-xs btn-default" id="select-all-days">
                                        Tous
                                    </button>
                                    <button type="button" class="btn btn-xs btn-default" id="clear-days">
                                        Aucun
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bulk-end-date">Date limite (optionnel)</label>
                                <input type="date" class="form-control" id="bulk-end-date" name="end_date">
                                <small class="text-muted">Si spécifiée, la création s'arrêtera à cette date</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="icon-warning"></i>
                        <strong>Attention :</strong> Cette action créera des disponibilités pour les jours sélectionnés sur la période choisie. 
                        Les créneaux existants ne seront pas modifiés.
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="execute-bulk-create-btn">
                    <i class="icon-magic"></i> Créer les disponibilités
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal pour copie de semaine *}
<div class="modal fade" id="copy-week-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
                <h4 class="modal-title">Copier une semaine</h4>
            </div>
            
            <div class="modal-body">
                <form id="copy-week-form">
                    <div class="form-group">
                        <label for="copy-booker">Élément *</label>
                        <select class="form-control" id="copy-booker" name="booker_id" required>
                            <option value="">Sélectionner un élément</option>
                            {foreach from=$bookers item=booker}
                                <option value="{$booker.id_booker}">
                                    {$booker.name|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="copy-source-week">Semaine source *</label>
                        <input type="week" class="form-control" id="copy-source-week" name="source_week" required>
                        <small class="text-muted">Sélectionnez la semaine à copier</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="copy-target-week">Semaine de destination *</label>
                        <input type="week" class="form-control" id="copy-target-week" name="target_week" required>
                        <small class="text-muted">Sélectionnez la semaine où coller</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="icon-info"></i>
                        Cette action copiera toutes les disponibilités de la semaine source vers la semaine de destination.
                        Les disponibilités existantes ne seront pas écrasées.
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="execute-copy-week-btn">
                    <i class="icon-copy"></i> Copier
                </button>
            </div>
        </div>
    </div>
</div>

{* Configuration JavaScript *}
<script>
var AvailabilityCalendar = {
    ajaxUrls: {$ajax_urls|json_encode},
    token: '{$token}',
    config: {$default_config|json_encode},
    currentDate: '{$current_date}',
    selectedDates: [],
    selectionMode: false,
    
    // Messages de traduction
    messages: {
        'confirm_delete': 'Êtes-vous sûr de vouloir supprimer cette disponibilité ?',
        'confirm_bulk_create': 'Créer les disponibilités pour les jours sélectionnés ?',
        'no_booker_selected': 'Veuillez sélectionner un élément',
        'no_days_selected': 'Veuillez sélectionner au moins un jour de la semaine',
        'invalid_date_range': 'La date de fin doit être postérieure à la date de début',
        'loading': 'Chargement...',
        'error': 'Erreur lors de l\'opération',
        'success_create': 'Disponibilité créée avec succès',
        'success_bulk_create': 'Disponibilités créées en lot',
        'success_update': 'Disponibilité mise à jour',
        'success_delete': 'Disponibilité supprimée',
        'success_copy': 'Semaine copiée avec succès',
        'conflict_reservations': 'Attention : des réservations existent sur cette période'
    }
};
</script>

{* Styles personnalisés *}
<style>
.fc-day-grid-container {
    cursor: pointer;
}

.fc-day.fc-selected {
    background-color: rgba(0, 123, 255, 0.1) !important;
    border: 2px solid #007bff !important;
}

.fc-event-availability {
    border-radius: 4px;
    padding: 2px 4px;
    margin: 1px 0;
}

.fc-event-availability:hover {
    transform: scale(1.02);
    transition: transform 0.2s;
}

.checkbox-list {
    max-height: 120px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 4px;
    background-color: #f9f9f9;
}

.checkbox-list label {
    display: block;
    margin-bottom: 5px;
    font-weight: normal;
}

.modal-lg {
    width: 90%;
    max-width: 900px;
}

#selection-mode-panel {
    border-left: 4px solid #17a2b8;
    background-color: #e7f3ff;
}

.btn-group .btn {
    margin-right: 5px;
}

.alert {
    border: none;
    border-radius: 8px;
}

.panel {
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.label {
    display: inline-block;
    width: 15px;
    height: 15px;
    margin-right: 5px;
    border-radius: 3px;
}

.fc-toolbar {
    margin-bottom: 20px;
}

.checkbox-inline {
    margin-right: 15px;
}
</style>