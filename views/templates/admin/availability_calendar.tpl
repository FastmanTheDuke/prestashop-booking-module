{*
* Template pour le calendrier de gestion des disponibilités
* Interface moderne avec FullCalendar et outils de gestion avancés
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar-plus-o"></i> 
        {l s='Calendrier des Disponibilités' mod='booking'}
        <div class="panel-heading-action">
            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                    <i class="icon-wrench"></i> Actions <span class="caret"></span>
                </button>
                <ul class="dropdown-menu" role="menu">
                    <li><a href="#" id="bulk-create-btn"><i class="icon-plus"></i> Création en lot</a></li>
                    <li><a href="#" id="copy-week-btn"><i class="icon-copy"></i> Copier une semaine</a></li>
                    <li><a href="#" id="recurring-btn"><i class="icon-repeat"></i> Créneaux récurrents</a></li>
                    <li class="divider"></li>
                    <li><a href="#" id="export-btn"><i class="icon-download"></i> Exporter</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="panel-body">
        {* Barre d'outils du calendrier *}
        <div class="calendar-toolbar">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="booker-filter">{l s='Filtrer par élément :' mod='booking'}</label>
                        <select id="booker-filter" class="form-control">
                            <option value="">{l s='Tous les éléments' mod='booking'}</option>
                            {foreach from=$bookers item=booker}
                                <option value="{$booker.id}">{$booker.name}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="view-selector">{l s='Vue :' mod='booking'}</label>
                        <div class="btn-group" id="view-selector">
                            <button type="button" class="btn btn-default" data-view="dayGridMonth">
                                <i class="icon-calendar"></i> {l s='Mois' mod='booking'}
                            </button>
                            <button type="button" class="btn btn-default" data-view="timeGridWeek">
                                <i class="icon-calendar-o"></i> {l s='Semaine' mod='booking'}
                            </button>
                            <button type="button" class="btn btn-default" data-view="timeGridDay">
                                <i class="icon-calendar-plus-o"></i> {l s='Jour' mod='booking'}
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="form-group">
                        <label>{l s='Navigation :' mod='booking'}</label>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default" id="prev-btn">
                                <i class="icon-chevron-left"></i> {l s='Précédent' mod='booking'}
                            </button>
                            <button type="button" class="btn btn-primary" id="today-btn">
                                <i class="icon-dot-circle-o"></i> {l s='Aujourd\'hui' mod='booking'}
                            </button>
                            <button type="button" class="btn btn-default" id="next-btn">
                                <i class="icon-chevron-right"></i> {l s='Suivant' mod='booking'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {* Zone du calendrier *}
        <div class="calendar-container">
            <div id="calendar-loading" class="text-center" style="padding: 50px;">
                <i class="icon-spinner icon-spin icon-3x"></i>
                <p>{l s='Chargement du calendrier...' mod='booking'}</p>
            </div>
            
            <div id="calendar" style="display: none;"></div>
        </div>
        
        {* Légende *}
        <div class="calendar-legend">
            <div class="row">
                <div class="col-md-12">
                    <h4>{l s='Légende :' mod='booking'}</h4>
                    <div class="legend-items">
                        <span class="legend-item">
                            <span class="legend-color" style="background-color: #28a745;"></span>
                            {l s='Disponible' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color" style="background-color: #ffc107;"></span>
                            {l s='Partiellement réservé' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color" style="background-color: #dc3545;"></span>
                            {l s='Complet' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color" style="background-color: #6c757d;"></span>
                            {l s='Indisponible' mod='booking'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* Statistiques des disponibilités *}
<div class="row">
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number" style="font-size: 2em; color: #28a745;">
                    {$availability_stats.total_slots|default:0}
                </div>
                <div class="metric-label">{l s='Créneaux total' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number" style="font-size: 2em; color: #007bff;">
                    {$availability_stats.available_slots|default:0}
                </div>
                <div class="metric-label">{l s='Disponibles' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number" style="font-size: 2em; color: #ffc107;">
                    {$availability_stats.partial_slots|default:0}
                </div>
                <div class="metric-label">{l s='Partiels' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number" style="font-size: 2em; color: #dc3545;">
                    {$availability_stats.full_slots|default:0}
                </div>
                <div class="metric-label">{l s='Complets' mod='booking'}</div>
            </div>
        </div>
    </div>
</div>

{* Modal de création/édition de disponibilité *}
<div class="modal fade" id="availability-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-calendar-plus-o"></i> 
                    <span id="modal-title">{l s='Nouvelle disponibilité' mod='booking'}</span>
                </h4>
            </div>
            
            <div class="modal-body">
                <form id="availability-form">
                    <input type="hidden" id="availability-id" name="id" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-booker" class="required">
                                    {l s='Élément :' mod='booking'}
                                </label>
                                <select id="availability-booker" name="id_booker" class="form-control" required>
                                    <option value="">{l s='Sélectionner un élément' mod='booking'}</option>
                                    {foreach from=$bookers item=booker}
                                        <option value="{$booker.id}" data-price="{$booker.price}" data-duration="{$booker.duration}">
                                            {$booker.name}
                                        </option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-max-bookings">
                                    {l s='Réservations maximum :' mod='booking'}
                                </label>
                                <input type="number" id="availability-max-bookings" name="max_bookings" 
                                       class="form-control" min="1" value="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-date-from" class="required">
                                    {l s='Date de début :' mod='booking'}
                                </label>
                                <input type="date" id="availability-date-from" name="date_from" 
                                       class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-date-to" class="required">
                                    {l s='Date de fin :' mod='booking'}
                                </label>
                                <input type="date" id="availability-date-to" name="date_to" 
                                       class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-time-from" class="required">
                                    {l s='Heure de début :' mod='booking'}
                                </label>
                                <input type="time" id="availability-time-from" name="time_from" 
                                       class="form-control" value="{$business_hours_start}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-time-to" class="required">
                                    {l s='Heure de fin :' mod='booking'}
                                </label>
                                <input type="time" id="availability-time-to" name="time_to" 
                                       class="form-control" value="{$business_hours_end}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-price-override">
                                    {l s='Prix spécial :' mod='booking'}
                                </label>
                                <div class="input-group">
                                    <input type="number" id="availability-price-override" name="price_override" 
                                           class="form-control" step="0.01" min="0">
                                    <span class="input-group-addon">€</span>
                                </div>
                                <p class="help-block">{l s='Laissez vide pour utiliser le prix par défaut' mod='booking'}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="availability-active">
                                    {l s='Statut :' mod='booking'}
                                </label>
                                <select id="availability-active" name="active" class="form-control">
                                    <option value="1">{l s='Actif' mod='booking'}</option>
                                    <option value="0">{l s='Inactif' mod='booking'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="availability-notes">
                            {l s='Notes :' mod='booking'}
                        </label>
                        <textarea id="availability-notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    {* Options récurrentes *}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                                <a data-toggle="collapse" href="#recurring-options">
                                    <i class="icon-repeat"></i> {l s='Options de récurrence' mod='booking'}
                                </a>
                            </h4>
                        </div>
                        <div id="recurring-options" class="panel-collapse collapse">
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="availability-recurring">
                                                {l s='Récurrence :' mod='booking'}
                                            </label>
                                            <select id="availability-recurring" name="recurring" class="form-control">
                                                <option value="0">{l s='Aucune' mod='booking'}</option>
                                                <option value="1">{l s='Récurrent' mod='booking'}</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="availability-recurring-type">
                                                {l s='Type :' mod='booking'}
                                            </label>
                                            <select id="availability-recurring-type" name="recurring_type" class="form-control">
                                                <option value="">{l s='Sélectionner' mod='booking'}</option>
                                                <option value="daily">{l s='Quotidien' mod='booking'}</option>
                                                <option value="weekly">{l s='Hebdomadaire' mod='booking'}</option>
                                                <option value="monthly">{l s='Mensuel' mod='booking'}</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="availability-recurring-end">
                                                {l s='Fin de récurrence :' mod='booking'}
                                            </label>
                                            <input type="date" id="availability-recurring-end" name="recurring_end" 
                                                   class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-primary" id="save-availability-btn">
                    <i class="icon-save"></i> {l s='Enregistrer' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal de création en lot *}
<div class="modal fade" id="bulk-create-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-plus"></i> {l s='Création en lot de disponibilités' mod='booking'}
                </h4>
            </div>
            
            <div class="modal-body">
                <form id="bulk-create-form">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        {l s='Créez plusieurs disponibilités en une seule fois pour un ou plusieurs éléments.' mod='booking'}
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-bookers" class="required">
                                    {l s='Éléments :' mod='booking'}
                                </label>
                                <select id="bulk-bookers" name="bookers[]" class="form-control" multiple required>
                                    {foreach from=$bookers item=booker}
                                        <option value="{$booker.id}">{$booker.name}</option>
                                    {/foreach}
                                </select>
                                <p class="help-block">{l s='Maintenez Ctrl pour sélectionner plusieurs éléments' mod='booking'}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-days">
                                    {l s='Jours de la semaine :' mod='booking'}
                                </label>
                                <div class="checkbox-group">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="1" checked> {l s='Lun' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="2" checked> {l s='Mar' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="3" checked> {l s='Mer' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="4" checked> {l s='Jeu' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="5" checked> {l s='Ven' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="6"> {l s='Sam' mod='booking'}
                                    </label>
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="days[]" value="0"> {l s='Dim' mod='booking'}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-date-start" class="required">
                                    {l s='Période de début :' mod='booking'}
                                </label>
                                <input type="date" id="bulk-date-start" name="date_start" 
                                       class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-date-end" class="required">
                                    {l s='Période de fin :' mod='booking'}
                                </label>
                                <input type="date" id="bulk-date-end" name="date_end" 
                                       class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-time-start" class="required">
                                    {l s='Heure de début :' mod='booking'}
                                </label>
                                <input type="time" id="bulk-time-start" name="time_start" 
                                       class="form-control" value="{$business_hours_start}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-time-end" class="required">
                                    {l s='Heure de fin :' mod='booking'}
                                </label>
                                <input type="time" id="bulk-time-end" name="time_end" 
                                       class="form-control" value="{$business_hours_end}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-slot-duration">
                                    {l s='Durée des créneaux (minutes) :' mod='booking'}
                                </label>
                                <input type="number" id="bulk-slot-duration" name="slot_duration" 
                                       class="form-control" value="{$default_duration}" min="15" step="15">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bulk-max-bookings">
                                    {l s='Réservations max par créneau :' mod='booking'}
                                </label>
                                <input type="number" id="bulk-max-bookings" name="max_bookings" 
                                       class="form-control" value="1" min="1">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-success" id="execute-bulk-create-btn">
                    <i class="icon-plus"></i> {l s='Créer les disponibilités' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal de copie de semaine *}
<div class="modal fade" id="copy-week-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-copy"></i> {l s='Copier une semaine' mod='booking'}
                </h4>
            </div>
            
            <div class="modal-body">
                <form id="copy-week-form">
                    <div class="form-group">
                        <label for="copy-source-week" class="required">
                            {l s='Semaine source :' mod='booking'}
                        </label>
                        <input type="week" id="copy-source-week" name="source_week" 
                               class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="copy-target-weeks" class="required">
                            {l s='Semaines de destination :' mod='booking'}
                        </label>
                        <select id="copy-target-weeks" name="target_weeks[]" class="form-control" multiple required>
                            {* Les options seront générées par JavaScript *}
                        </select>
                        <p class="help-block">{l s='Maintenez Ctrl pour sélectionner plusieurs semaines' mod='booking'}</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="icon-warning"></i>
                        {l s='Les disponibilités existantes ne seront pas écrasées.' mod='booking'}
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-primary" id="execute-copy-week-btn">
                    <i class="icon-copy"></i> {l s='Copier' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Configuration JavaScript *}
<script>
var AvailabilityCalendar = {
    ajaxUrls: {$ajax_urls|json_encode},
    config: {
        locale: 'fr',
        business_hours: {
            startTime: '{$business_hours_start}',
            endTime: '{$business_hours_end}',
            daysOfWeek: [1, 2, 3, 4, 5] // Lundi à vendredi par défaut
        },
        default_view: 'timeGridWeek',
        slot_duration: '{$default_duration}',
        current_date: '{$current_date}'
    },
    bookers: {$bookers|json_encode},
    currentDate: '{$current_date}',
    selectedDates: [],
    selectionMode: false,
    
    // Messages de traduction
    messages: {
        'confirm_delete': '{l s='Êtes-vous sûr de vouloir supprimer cette disponibilité ?' mod='booking' js=1}',
        'confirm_bulk_create': '{l s='Créer les disponibilités pour les jours sélectionnés ?' mod='booking' js=1}',
        'confirm_copy_week': '{l s='Copier les disponibilités de cette semaine ?' mod='booking' js=1}',
        'error_loading': '{l s='Erreur lors du chargement des données' mod='booking' js=1}',
        'success_save': '{l s='Disponibilité enregistrée avec succès' mod='booking' js=1}',
        'success_delete': '{l s='Disponibilité supprimée avec succès' mod='booking' js=1}',
        'success_bulk_create': '{l s='Disponibilités créées avec succès' mod='booking' js=1}',
        'success_copy_week': '{l s='Semaine copiée avec succès' mod='booking' js=1}',
        'validation_required': '{l s='Veuillez remplir tous les champs requis' mod='booking' js=1}',
        'validation_date_range': '{l s='La date de fin doit être postérieure à la date de début' mod='booking' js=1}',
        'validation_time_range': '{l s='L\'heure de fin doit être postérieure à l\'heure de début' mod='booking' js=1}'
    }
};
</script>

<style>
.calendar-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.calendar-toolbar {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.calendar-legend {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: inline-block;
}

.metric-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.metric-label {
    font-size: 0.9em;
    color: #666;
}

.checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.panel-heading-action {
    float: right;
}

#calendar {
    min-height: 600px;
}

.fc-event {
    cursor: pointer;
    border: none !important;
    font-size: 12px;
}

.fc-event-title {
    font-weight: 600;
}

.fc-daygrid-event {
    margin-bottom: 2px;
}

.availability-available { background-color: #28a745 !important; }
.availability-partial { background-color: #ffc107 !important; }
.availability-full { background-color: #dc3545 !important; }
.availability-inactive { background-color: #6c757d !important; }
</style>