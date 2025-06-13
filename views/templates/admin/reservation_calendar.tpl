{*
* Template pour le calendrier de gestion des réservations
* Interface de validation et gestion des statuts
*}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar-check-o"></i> 
        {l s='Calendrier des Réservations' mod='booking'}
        <div class="panel-heading-action">
            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                    <i class="icon-cog"></i> Actions <span class="caret"></span>
                </button>
                <ul class="dropdown-menu" role="menu">
                    <li><a href="#" id="bulk-validate-btn"><i class="icon-check"></i> Validation en lot</a></li>
                    <li><a href="#" id="bulk-cancel-btn"><i class="icon-times"></i> Annulation en lot</a></li>
                    <li><a href="#" id="bulk-send-notifications-btn"><i class="icon-envelope"></i> Notifications en lot</a></li>
                    <li class="divider"></li>
                    <li><a href="#" id="export-reservations-btn"><i class="icon-download"></i> Exporter</a></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="panel-body">
        {* Barre d'outils du calendrier *}
        <div class="calendar-toolbar">
            <div class="row">
                <div class="col-md-3">
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
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="status-filter">{l s='Filtrer par statut :' mod='booking'}</label>
                        <select id="status-filter" class="form-control">
                            <option value="">{l s='Tous les statuts' mod='booking'}</option>
                            <option value="pending">{l s='En attente' mod='booking'}</option>
                            <option value="confirmed">{l s='Confirmé' mod='booking'}</option>
                            <option value="paid">{l s='Payé' mod='booking'}</option>
                            <option value="cancelled">{l s='Annulé' mod='booking'}</option>
                            <option value="completed">{l s='Terminé' mod='booking'}</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
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
                
                <div class="col-md-3">
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
        
        {* Actions rapides *}
        <div class="quick-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success" id="quick-validate-btn">
                    <i class="icon-check"></i> {l s='Valider sélectionnées' mod='booking'}
                </button>
                <button type="button" class="btn btn-danger" id="quick-cancel-btn">
                    <i class="icon-times"></i> {l s='Annuler sélectionnées' mod='booking'}
                </button>
                <button type="button" class="btn btn-info" id="quick-create-orders-btn">
                    <i class="icon-shopping-cart"></i> {l s='Créer commandes' mod='booking'}
                </button>
                <button type="button" class="btn btn-warning" id="quick-send-reminders-btn">
                    <i class="icon-bell"></i> {l s='Envoyer rappels' mod='booking'}
                </button>
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
                    <h4>{l s='Légende des statuts :' mod='booking'}</h4>
                    <div class="legend-items">
                        <span class="legend-item">
                            <span class="legend-color reservation-pending"></span>
                            {l s='En attente' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color reservation-confirmed"></span>
                            {l s='Confirmé' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color reservation-paid"></span>
                            {l s='Payé' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color reservation-cancelled"></span>
                            {l s='Annulé' mod='booking'}
                        </span>
                        <span class="legend-item">
                            <span class="legend-color reservation-completed"></span>
                            {l s='Terminé' mod='booking'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* Statistiques des réservations *}
<div class="row">
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number reservation-pending-color">
                    {$reservation_stats.pending|default:0}
                </div>
                <div class="metric-label">{l s='En attente' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number reservation-confirmed-color">
                    {$reservation_stats.confirmed|default:0}
                </div>
                <div class="metric-label">{l s='Confirmés' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number reservation-paid-color">
                    {$reservation_stats.paid|default:0}
                </div>
                <div class="metric-label">{l s='Payés' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number reservation-cancelled-color">
                    {$reservation_stats.cancelled|default:0}
                </div>
                <div class="metric-label">{l s='Annulés' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number reservation-completed-color">
                    {$reservation_stats.completed|default:0}
                </div>
                <div class="metric-label">{l s='Terminés' mod='booking'}</div>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="panel">
            <div class="panel-body text-center">
                <div class="metric-number" style="color: #28a745;">
                    {$reservation_stats.revenue|default:'0.00'}€
                </div>
                <div class="metric-label">{l s='CA du mois' mod='booking'}</div>
            </div>
        </div>
    </div>
</div>

{* Modal de détails de réservation *}
<div class="modal fade" id="reservation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-calendar-check-o"></i> 
                    <span id="reservation-modal-title">{l s='Détails de la réservation' mod='booking'}</span>
                </h4>
            </div>
            
            <div class="modal-body">
                <div id="reservation-details">
                    {* Le contenu sera chargé dynamiquement *}
                </div>
                
                <form id="reservation-form" style="display: none;">
                    <input type="hidden" id="reservation-id" name="id" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reservation-status" class="required">
                                    {l s='Statut :' mod='booking'}
                                </label>
                                <select id="reservation-status" name="status" class="form-control" required>
                                    <option value="pending">{l s='En attente' mod='booking'}</option>
                                    <option value="confirmed">{l s='Confirmé' mod='booking'}</option>
                                    <option value="paid">{l s='Payé' mod='booking'}</option>
                                    <option value="cancelled">{l s='Annulé' mod='booking'}</option>
                                    <option value="completed">{l s='Terminé' mod='booking'}</option>
                                    <option value="refunded">{l s='Remboursé' mod='booking'}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reservation-payment-status">
                                    {l s='Statut paiement :' mod='booking'}
                                </label>
                                <select id="reservation-payment-status" name="payment_status" class="form-control">
                                    <option value="pending">{l s='En attente' mod='booking'}</option>
                                    <option value="authorized">{l s='Autorisé' mod='booking'}</option>
                                    <option value="captured">{l s='Capturé' mod='booking'}</option>
                                    <option value="cancelled">{l s='Annulé' mod='booking'}</option>
                                    <option value="refunded">{l s='Remboursé' mod='booking'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reservation-admin-notes">
                            {l s='Notes administrateur :' mod='booking'}
                        </label>
                        <textarea id="reservation-admin-notes" name="admin_notes" 
                                  class="form-control" rows="3" 
                                  placeholder="{l s='Notes internes visibles uniquement par les administrateurs' mod='booking'}"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <div class="btn-group" id="reservation-actions">
                    <button type="button" class="btn btn-success" id="validate-reservation-btn">
                        <i class="icon-check"></i> {l s='Valider' mod='booking'}
                    </button>
                    <button type="button" class="btn btn-warning" id="edit-reservation-btn">
                        <i class="icon-edit"></i> {l s='Modifier' mod='booking'}
                    </button>
                    <button type="button" class="btn btn-info" id="create-order-btn">
                        <i class="icon-shopping-cart"></i> {l s='Créer commande' mod='booking'}
                    </button>
                    <button type="button" class="btn btn-primary" id="send-notification-btn">
                        <i class="icon-envelope"></i> {l s='Notification' mod='booking'}
                    </button>
                    <button type="button" class="btn btn-danger" id="cancel-reservation-btn">
                        <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                    </button>
                </div>
                
                <div class="btn-group" id="reservation-form-actions" style="display: none;">
                    <button type="button" class="btn btn-default" id="cancel-edit-btn">
                        <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                    </button>
                    <button type="button" class="btn btn-primary" id="save-reservation-btn">
                        <i class="icon-save"></i> {l s='Enregistrer' mod='booking'}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{* Modal de validation en lot *}
<div class="modal fade" id="bulk-validate-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-check"></i> {l s='Validation en lot' mod='booking'}
                </h4>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="icon-info-circle"></i>
                    <span id="bulk-validation-count"></span>
                </div>
                
                <form id="bulk-validate-form">
                    <div class="form-group">
                        <label for="bulk-validate-auto-create-order">
                            <input type="checkbox" id="bulk-validate-auto-create-order" name="auto_create_order" checked>
                            {l s='Créer automatiquement les commandes' mod='booking'}
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk-validate-send-notification">
                            <input type="checkbox" id="bulk-validate-send-notification" name="send_notification" checked>
                            {l s='Envoyer les notifications de confirmation' mod='booking'}
                        </label>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-success" id="execute-bulk-validate-btn">
                    <i class="icon-check"></i> {l s='Valider les réservations' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal d'annulation en lot *}
<div class="modal fade" id="bulk-cancel-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-times"></i> {l s='Annulation en lot' mod='booking'}
                </h4>
            </div>
            
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="icon-warning"></i>
                    <span id="bulk-cancel-count"></span>
                    <p>{l s='Cette action est irréversible. Les créneaux seront libérés.' mod='booking'}</p>
                </div>
                
                <form id="bulk-cancel-form">
                    <div class="form-group">
                        <label for="bulk-cancel-reason" class="required">
                            {l s='Motif d\'annulation :' mod='booking'}
                        </label>
                        <select id="bulk-cancel-reason" name="cancel_reason" class="form-control" required>
                            <option value="">{l s='Sélectionner un motif' mod='booking'}</option>
                            <option value="admin_cancel">{l s='Annulation administrative' mod='booking'}</option>
                            <option value="no_show">{l s='Absence du client' mod='booking'}</option>
                            <option value="force_majeure">{l s='Force majeure' mod='booking'}</option>
                            <option value="maintenance">{l s='Maintenance / Indisponibilité' mod='booking'}</option>
                            <option value="other">{l s='Autre motif' mod='booking'}</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk-cancel-notes">
                            {l s='Notes additionnelles :' mod='booking'}
                        </label>
                        <textarea id="bulk-cancel-notes" name="cancel_notes" 
                                  class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk-cancel-send-notification">
                            <input type="checkbox" id="bulk-cancel-send-notification" name="send_notification" checked>
                            {l s='Envoyer les notifications d\'annulation' mod='booking'}
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="bulk-cancel-process-refund">
                            <input type="checkbox" id="bulk-cancel-process-refund" name="process_refund">
                            {l s='Traiter les remboursements automatiquement' mod='booking'}
                        </label>
                        <p class="help-block">{l s='Nécessite la configuration du module de paiement' mod='booking'}</p>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-danger" id="execute-bulk-cancel-btn">
                    <i class="icon-times"></i> {l s='Annuler les réservations' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Modal d'envoi de notifications *}
<div class="modal fade" id="send-notification-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="icon-envelope"></i> {l s='Envoyer une notification' mod='booking'}
                </h4>
            </div>
            
            <div class="modal-body">
                <form id="send-notification-form">
                    <input type="hidden" id="notification-reservation-id" name="reservation_id" value="">
                    
                    <div class="form-group">
                        <label for="notification-type" class="required">
                            {l s='Type de notification :' mod='booking'}
                        </label>
                        <select id="notification-type" name="notification_type" class="form-control" required>
                            <option value="">{l s='Sélectionner un type' mod='booking'}</option>
                            <option value="confirmation">{l s='Confirmation de réservation' mod='booking'}</option>
                            <option value="reminder">{l s='Rappel de réservation' mod='booking'}</option>
                            <option value="modification">{l s='Modification de réservation' mod='booking'}</option>
                            <option value="cancellation">{l s='Annulation de réservation' mod='booking'}</option>
                            <option value="custom">{l s='Message personnalisé' mod='booking'}</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="custom-message-group" style="display: none;">
                        <label for="notification-custom-message" class="required">
                            {l s='Message personnalisé :' mod='booking'}
                        </label>
                        <textarea id="notification-custom-message" name="custom_message" 
                                  class="form-control" rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notification-send-sms">
                            <input type="checkbox" id="notification-send-sms" name="send_sms">
                            {l s='Envoyer également par SMS (si configuré)' mod='booking'}
                        </label>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="icon-times"></i> {l s='Annuler' mod='booking'}
                </button>
                <button type="button" class="btn btn-primary" id="execute-send-notification-btn">
                    <i class="icon-envelope"></i> {l s='Envoyer' mod='booking'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Configuration JavaScript *}
<script>
var ReservationCalendar = {
    ajaxUrls: {$ajax_urls|json_encode},
    config: {
        locale: 'fr',
        business_hours: {
            startTime: '{$business_hours_start}',
            endTime: '{$business_hours_end}',
            daysOfWeek: [1, 2, 3, 4, 5, 6, 7]
        },
        default_view: 'timeGridWeek',
        current_date: '{$current_date}'
    },
    bookers: {$bookers|json_encode},
    statuses: {$statuses|json_encode},
    currentDate: '{$current_date}',
    selectedReservations: [],
    
    // Messages de traduction
    messages: {
        'confirm_validate': '{l s='Confirmer la validation de cette réservation ?' mod='booking' js=1}',
        'confirm_cancel': '{l s='Confirmer l\'annulation de cette réservation ?' mod='booking' js=1}',
        'confirm_bulk_validate': '{l s='Valider les réservations sélectionnées ?' mod='booking' js=1}',
        'confirm_bulk_cancel': '{l s='Annuler les réservations sélectionnées ?' mod='booking' js=1}',
        'error_loading': '{l s='Erreur lors du chargement des données' mod='booking' js=1}',
        'success_validate': '{l s='Réservation validée avec succès' mod='booking' js=1}',
        'success_cancel': '{l s='Réservation annulée avec succès' mod='booking' js=1}',
        'success_bulk_validate': '{l s='Réservations validées avec succès' mod='booking' js=1}',
        'success_bulk_cancel': '{l s='Réservations annulées avec succès' mod='booking' js=1}',
        'success_notification_sent': '{l s='Notification envoyée avec succès' mod='booking' js=1}',
        'success_order_created': '{l s='Commande créée avec succès' mod='booking' js=1}',
        'validation_required': '{l s='Veuillez remplir tous les champs requis' mod='booking' js=1}',
        'no_selection': '{l s='Aucune réservation sélectionnée' mod='booking' js=1}',
        'bulk_validate_count': '{l s='%d réservation(s) sélectionnée(s) pour validation' mod='booking' js=1}',
        'bulk_cancel_count': '{l s='%d réservation(s) sélectionnée(s) pour annulation' mod='booking' js=1}'
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

.quick-actions {
    margin-bottom: 20px;
    padding: 10px;
    background: #fff3cd;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
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
    font-size: 2em;
}

.metric-label {
    font-size: 0.9em;
    color: #666;
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

/* Couleurs des statuts de réservation */
.reservation-pending,
.legend-color.reservation-pending { background-color: #ffc107 !important; }
.reservation-confirmed,
.legend-color.reservation-confirmed { background-color: #17a2b8 !important; }
.reservation-paid,
.legend-color.reservation-paid { background-color: #28a745 !important; }
.reservation-cancelled,
.legend-color.reservation-cancelled { background-color: #dc3545 !important; }
.reservation-completed,
.legend-color.reservation-completed { background-color: #6f42c1 !important; }
.reservation-refunded,
.legend-color.reservation-refunded { background-color: #fd7e14 !important; }

/* Couleurs des métriques */
.reservation-pending-color { color: #ffc107; }
.reservation-confirmed-color { color: #17a2b8; }
.reservation-paid-color { color: #28a745; }
.reservation-cancelled-color { color: #dc3545; }
.reservation-completed-color { color: #6f42c1; }

/* Style pour les réservations sélectionnées */
.fc-event.selected {
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.5) !important;
    z-index: 999;
}

/* Tooltips */
.fc-event {
    position: relative;
}

.fc-event:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}

/* Responsive */
@media (max-width: 768px) {
    .legend-items {
        flex-direction: column;
        gap: 10px;
    }
    
    .quick-actions .btn-group {
        flex-direction: column;
        width: 100%;
    }
    
    .quick-actions .btn {
        margin-bottom: 5px;
    }
}
</style>