<div class="panel">
    <div class="panel-heading">
        <i class="icon-calendar"></i> Calendrier des Réservations
    </div>
    <div class="panel-body">
        
        <!-- Contrôles du calendrier -->
        <div class="calendar-controls row mb-3">
            <div class="col-md-2">
                <label for="booker-select">Élément :</label>
                <select id="booker-select" class="form-control">
                    <option value="">-- Tous --</option>
                    {foreach from=$bookers item=booker}
                        <option value="{$booker.id_booker}">{$booker.name}</option>
                    {/foreach}
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status-filter">Statut :</label>
                <select id="status-filter" class="form-control">
                    <option value="all">-- Tous --</option>
                    {foreach from=$statuses key=status_id item=status_label}
                        <option value="{$status_id}">{$status_label}</option>
                    {/foreach}
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="calendar-view">Vue :</label>
                <select id="calendar-view" class="form-control">
                    <option value="month">Mois</option>
                    <option value="week">Semaine</option>
                    <option value="day">Jour</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="date-navigation">Navigation :</label>
                <div class="input-group">
                    <button id="prev-period" class="btn btn-default">
                        <i class="icon-chevron-left"></i>
                    </button>
                    <input type="text" id="current-period" class="form-control text-center" readonly>
                    <button id="next-period" class="btn btn-default">
                        <i class="icon-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-3">
                <label>&nbsp;</label>
                <div>
                    <button id="today-btn" class="btn btn-info">Aujourd'hui</button>
                    <button id="create-reservation" class="btn btn-success">
                        <i class="icon-plus"></i> Nouvelle réservation
                    </button>
                    <button id="refresh-calendar" class="btn btn-default">
                        <i class="icon-refresh"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Outils de gestion -->
        <div class="calendar-tools row mb-3">
            <div class="col-md-6">
                <div class="btn-group">
                    <button id="select-mode" class="btn btn-default active" data-mode="single">
                        <i class="icon-mouse-pointer"></i> Voir détails
                    </button>
                    <button id="multi-select-mode" class="btn btn-default" data-mode="multi">
                        <i class="icon-th"></i> Sélection multiple
                    </button>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="btn-group" id="bulk-actions" style="display: none;">
                    <button id="bulk-accept" class="btn btn-success">
                        <i class="icon-check"></i> Accepter
                    </button>
                    <button id="bulk-cancel" class="btn btn-warning">
                        <i class="icon-times"></i> Annuler
                    </button>
                    <button id="bulk-delete" class="btn btn-danger">
                        <i class="icon-trash"></i> Supprimer
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="calendar-stats row mb-3">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <div class="row">
                        <div class="col-md-2">
                            <strong>En attente:</strong> <span id="stat-pending">0</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Acceptées:</strong> <span id="stat-accepted">0</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Payées:</strong> <span id="stat-paid">0</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Annulées:</strong> <span id="stat-cancelled">0</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Expirées:</strong> <span id="stat-expired">0</span>
                        </div>
                        <div class="col-md-2">
                            <strong>Total:</strong> <span id="stat-total">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendrier principal -->
        <div id="calendar-container" class="calendar-container">
            <div id="calendar-loading" class="text-center">
                <i class="icon-spinner icon-spin"></i> Chargement du calendrier...
            </div>
            
            <div id="calendar-content" style="display: none;">
                <!-- Le contenu du calendrier sera injecté ici via JavaScript -->
            </div>
        </div>

        <!-- Légende -->
        <div class="calendar-legend mt-3">
            <h4>Légende :</h4>
            <div class="legend-items">
                <span class="legend-item">
                    <span class="legend-color reservation-pending"></span>
                    En attente
                </span>
                <span class="legend-item">
                    <span class="legend-color reservation-accepted"></span>
                    Acceptée
                </span>
                <span class="legend-item">
                    <span class="legend-color reservation-paid"></span>
                    Payée
                </span>
                <span class="legend-item">
                    <span class="legend-color reservation-cancelled"></span>
                    Annulée
                </span>
                <span class="legend-item">
                    <span class="legend-color reservation-expired"></span>
                    Expirée
                </span>
                <span class="legend-item">
                    <span class="legend-color reservation-selected"></span>
                    Sélectionnée
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour créer/modifier une réservation -->
<div class="modal fade" id="reservation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Nouvelle réservation</h4>
            </div>
            <div class="modal-body">
                <form id="reservation-form">
                    <input type="hidden" id="reservation-id" name="reservation_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reservation-booker-id">Élément à réserver :</label>
                                <select id="reservation-booker-id" name="booker_id" class="form-control" required>
                                    <option value="">Sélectionner...</option>
                                    {foreach from=$bookers item=booker}
                                        <option value="{$booker.id_booker}">{$booker.name}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reservation-status">Statut :</label>
                                <select id="reservation-status" name="status" class="form-control" required>
                                    {foreach from=$statuses key=status_id item=status_label}
                                        <option value="{$status_id}">{$status_label}</option>
                                    {/foreach}
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reservation-date">Date :</label>
                                <input type="date" id="reservation-date" name="date_reserved" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reservation-hour-from">Heure de début :</label>
                                <select id="reservation-hour-from" name="hour_from" class="form-control" required>
                                    {for $hour=0 to 23}
                                        <option value="{$hour}">{$hour|string_format:"%02d"}:00</option>
                                    {/for}
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reservation-hour-to">Heure de fin :</label>
                                <select id="reservation-hour-to" name="hour_to" class="form-control" required>
                                    {for $hour=1 to 24}
                                        <option value="{$hour}">{$hour|string_format:"%02d"}:00</option>
                                    {/for}
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div id="available-slots-info" class="alert alert-info" style="display: none;">
                        <strong>Créneaux disponibles :</strong>
                        <div id="available-slots-list"></div>
                    </div>
                    
                    <h5>Informations client :</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customer-name">Nom du client :</label>
                                <input type="text" id="customer-name" name="customer_name" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customer-email">Email :</label>
                                <input type="email" id="customer-email" name="customer_email" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="customer-phone">Téléphone :</label>
                                <input type="tel" id="customer-phone" name="customer_phone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reservation-notes">Notes :</label>
                                <textarea id="reservation-notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" id="check-availability" class="btn btn-info">Vérifier disponibilité</button>
                <button type="button" id="save-reservation" class="btn btn-primary">Sauvegarder</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les détails d'une réservation -->
<div class="modal fade" id="reservation-details-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Détails de la réservation</h4>
            </div>
            <div class="modal-body">
                <div id="reservation-details-content">
                    <!-- Le contenu sera rempli via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <div class="btn-group">
                    <button type="button" id="change-status-pending" class="btn btn-warning" data-status="0">
                        En attente
                    </button>
                    <button type="button" id="change-status-accepted" class="btn btn-info" data-status="1">
                        Accepter
                    </button>
                    <button type="button" id="change-status-paid" class="btn btn-success" data-status="2">
                        Marquer payée
                    </button>
                    <button type="button" id="change-status-cancelled" class="btn btn-danger" data-status="3">
                        Annuler
                    </button>
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                <button type="button" id="edit-reservation" class="btn btn-primary">Modifier</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour actions en lot -->
<div class="modal fade" id="bulk-reservations-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Action en lot</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <span id="selected-reservations-count">0</span> réservation(s) sélectionnée(s)
                </div>
                
                <div id="bulk-action-content">
                    <!-- Le contenu sera adapté selon l'action -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" id="confirm-bulk-reservations" class="btn btn-primary">Confirmer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Variables globales
    window.ReservationCalendar = {
        ajaxUrl: '{$ajax_url}',
        currentBookerId: '',
        currentYear: {$current_year},
        currentMonth: {$current_month},
        currentView: 'month',
        statusFilter: 'all',
        selectionMode: 'single',
        selectedReservations: [],
        statuses: {
            {foreach from=$statuses key=status_id item=status_label}
                '{$status_id}': '{$status_label}'{if !$status_label@last},{/if}
            {/foreach}
        }
    };
</script>

<style>
.calendar-container {
    min-height: 500px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
}

.calendar-controls .form-group {
    margin-bottom: 10px;
}

.calendar-tools, .calendar-stats {
    border-top: 1px solid #eee;
    border-bottom: 1px solid #eee;
    padding: 10px 0;
}

.calendar-legend {
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 3px;
    border: 1px solid #ccc;
}

.reservation-pending {
    background-color: #fff3cd;
    border-color: #ffeaa7;
}

.reservation-accepted {
    background-color: #cce5ff;
    border-color: #80bdff;
}

.reservation-paid {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.reservation-cancelled {
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.reservation-expired {
    background-color: #e2e3e5;
    border-color: #d6d8db;
}

.reservation-selected {
    background-color: #fff;
    border: 2px solid #007cba;
}

.calendar-day {
    position: relative;
    min-height: 120px;
    border: 1px solid #ddd;
    padding: 5px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.calendar-day:hover {
    background-color: #f8f9fa;
}

.calendar-day.selected {
    border: 2px solid #007cba;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.reservation-item {
    font-size: 0.75em;
    padding: 2px 4px;
    margin: 1px 0;
    border-radius: 2px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.reservation-item:hover {
    opacity: 0.8;
}

.reservation-item.selected {
    border: 1px solid #007cba;
    box-shadow: 0 0 3px rgba(0, 124, 186, 0.5);
}

.multi-select-mode .reservation-item {
    cursor: crosshair;
}

.btn-group .btn.active {
    background-color: #007cba;
    color: white;
}

.calendar-stats .alert {
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .calendar-controls .col-md-2,
    .calendar-controls .col-md-3 {
        margin-bottom: 10px;
    }
    
    .legend-items {
        flex-direction: column;
        gap: 5px;
    }
    
    .calendar-stats .row .col-md-2 {
        margin-bottom: 5px;
    }
}
</style>