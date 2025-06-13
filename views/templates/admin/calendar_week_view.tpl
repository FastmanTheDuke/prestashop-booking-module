{* Vue semaine du calendrier de réservations *}
<div class="calendar-week-view">
    <div class="calendar-header">
        <div class="view-controls">
            <button class="btn btn-default btn-sm view-btn" data-view="month">
                <i class="icon-calendar"></i> Mois
            </button>
            <button class="btn btn-primary btn-sm view-btn active" data-view="week">
                <i class="icon-th"></i> Semaine
            </button>
            <button class="btn btn-default btn-sm view-btn" data-view="day">
                <i class="icon-list"></i> Jour
            </button>
        </div>
        
        <div class="navigation-controls">
            <button class="btn btn-default nav-btn" data-action="prev">
                <i class="icon-chevron-left"></i>
            </button>
            <h3 class="current-period">{$week_start|date_format:'d/m/Y'} - {$week_end|date_format:'d/m/Y'}</h3>
            <button class="btn btn-default nav-btn" data-action="next">
                <i class="icon-chevron-right"></i>
            </button>
            <button class="btn btn-default nav-btn" data-action="today">Aujourd'hui</button>
        </div>
        
        <div class="booker-filter">
            <select class="form-control booker-selector" id="week_booker_filter">
                <option value="">Tous les éléments</option>
                {foreach $bookers as $booker}
                    <option value="{$booker.id_booker}" {if $selected_booker == $booker.id_booker}selected{/if}>
                        {$booker.name|escape:'html':'UTF-8'}
                    </option>
                {/foreach}
            </select>
        </div>
    </div>
    
    <div class="calendar-content">
        <div class="time-column">
            <div class="time-header"></div>
            {for $hour=0 to 23}
                <div class="time-slot" data-hour="{$hour}">
                    {$hour|string_format:"%02d"}:00
                </div>
            {/for}
        </div>
        
        <div class="days-container">
            {foreach $week_days as $day_data}
                <div class="day-column" data-date="{$day_data.date}">
                    <div class="day-header">
                        <div class="day-name">{$day_data.day_name}</div>
                        <div class="day-number {if $day_data.is_today}today{/if}">
                            {$day_data.day_number}
                        </div>
                    </div>
                    
                    <div class="day-content">
                        {for $hour=0 to 23}
                            <div class="hour-slot" 
                                 data-date="{$day_data.date}" 
                                 data-hour="{$hour}"
                                 {if isset($day_data.availability[$hour]) && $day_data.availability[$hour]}
                                    class="available"
                                 {/if}>
                                
                                {* Afficher les réservations pour cette heure *}
                                {if isset($day_data.reservations[$hour])}
                                    {foreach $day_data.reservations[$hour] as $reservation}
                                        <div class="reservation-block status-{$reservation.status}"
                                             data-id="{$reservation.id_reserved}"
                                             style="height: {($reservation.duration * 60)|default:60}px;">
                                            <div class="reservation-content">
                                                <div class="reservation-title">
                                                    {$reservation.customer_firstname} {$reservation.customer_lastname}
                                                </div>
                                                <div class="reservation-time">
                                                    {$reservation.hour_from|string_format:"%02d"}h-{$reservation.hour_to|string_format:"%02d"}h
                                                </div>
                                                <div class="reservation-ref">#{$reservation.booking_reference}</div>
                                            </div>
                                        </div>
                                    {/foreach}
                                {/if}
                            </div>
                        {/for}
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>

{* Modal de détails de réservation *}
<div class="modal fade" id="reservation-details-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Détails de la réservation</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Informations client</h5>
                        <dl class="dl-horizontal">
                            <dt>Nom :</dt>
                            <dd class="customer-name"></dd>
                            <dt>Email :</dt>
                            <dd class="customer-email"></dd>
                            <dt>Téléphone :</dt>
                            <dd class="customer-phone"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h5>Détails réservation</h5>
                        <dl class="dl-horizontal">
                            <dt>Référence :</dt>
                            <dd class="booking-reference"></dd>
                            <dt>Date :</dt>
                            <dd class="booking-date"></dd>
                            <dt>Horaires :</dt>
                            <dd class="booking-time"></dd>
                            <dt>Statut :</dt>
                            <dd class="booking-status"></dd>
                        </dl>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <h5>Message client</h5>
                        <p class="customer-message"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="btn-group">
                    <button type="button" class="btn btn-success action-btn" data-action="accept">
                        <i class="icon-check"></i> Accepter
                    </button>
                    <button type="button" class="btn btn-warning action-btn" data-action="modify">
                        <i class="icon-edit"></i> Modifier
                    </button>
                    <button type="button" class="btn btn-danger action-btn" data-action="cancel">
                        <i class="icon-remove"></i> Annuler
                    </button>
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

{* Modal de création rapide de réservation *}
<div class="modal fade" id="quick-booking-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Créer une réservation</h4>
            </div>
            <form id="quick-booking-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Date et heure</label>
                        <p class="form-control-static booking-datetime"></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="quick_booker">Élément à réserver</label>
                        <select class="form-control" id="quick_booker" name="id_booker" required>
                            <option value="">Sélectionner...</option>
                            {foreach $bookers as $booker}
                                <option value="{$booker.id_booker}">{$booker.name|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="quick_firstname">Prénom</label>
                                <input type="text" class="form-control" id="quick_firstname" name="firstname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="quick_lastname">Nom</label>
                                <input type="text" class="form-control" id="quick_lastname" name="lastname" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="quick_email">Email</label>
                        <input type="email" class="form-control" id="quick_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quick_phone">Téléphone</label>
                        <input type="tel" class="form-control" id="quick_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="quick_message">Message</label>
                        <textarea class="form-control" id="quick_message" name="message" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auto_confirm" value="1">
                            Confirmer automatiquement
                        </label>
                    </div>
                    
                    <input type="hidden" name="date_reserved" id="quick_date">
                    <input type="hidden" name="hour_from" id="quick_hour_from">
                    <input type="hidden" name="hour_to" id="quick_hour_to">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Créer la réservation</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.calendar-week-view {
    background: white;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.view-controls .view-btn.active {
    background: #007680;
    border-color: #007680;
    color: white;
}

.calendar-content {
    display: flex;
    min-height: 600px;
}

.time-column {
    width: 80px;
    border-right: 1px solid #dee2e6;
    background: #f8f9fa;
}

.time-header {
    height: 80px;
    border-bottom: 1px solid #dee2e6;
}

.time-slot {
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #dee2e6;
    font-size: 0.85rem;
    color: #6c757d;
}

.days-container {
    flex: 1;
    display: flex;
}

.day-column {
    flex: 1;
    border-right: 1px solid #dee2e6;
}

.day-column:last-child {
    border-right: none;
}

.day-header {
    height: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #dee2e6;
    background: #f8f9fa;
}

.day-name {
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
}

.day-number {
    font-size: 1.5rem;
    font-weight: 600;
    color: #495057;
}

.day-number.today {
    color: #007680;
    background: rgba(0, 118, 128, 0.1);
    border-radius: 50%;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hour-slot {
    height: 60px;
    border-bottom: 1px solid #dee2e6;
    position: relative;
    cursor: pointer;
    transition: background-color 0.2s;
}

.hour-slot.available {
    background: rgba(0, 118, 128, 0.05);
}

.hour-slot.available:hover {
    background: rgba(0, 118, 128, 0.1);
}

.hour-slot:hover::after {
    content: '+';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    color: #007680;
    font-weight: bold;
}

.reservation-block {
    position: absolute;
    left: 2px;
    right: 2px;
    top: 2px;
    border-radius: 4px;
    padding: 4px 6px;
    font-size: 0.75rem;
    cursor: pointer;
    overflow: hidden;
    z-index: 10;
}

.reservation-block.status-0 {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.reservation-block.status-1 {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.reservation-block.status-2 {
    background: #d1ecf1;
    border: 1px solid #b6d4da;
    color: #0c5460;
}

.reservation-block.status-3 {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.reservation-content {
    line-height: 1.2;
}

.reservation-title {
    font-weight: 600;
    margin-bottom: 2px;
}

.reservation-time,
.reservation-ref {
    font-size: 0.7rem;
    opacity: 0.8;
}

.reservation-block:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 20;
}

@media (max-width: 768px) {
    .calendar-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .time-column {
        width: 60px;
    }
    
    .day-header {
        height: 60px;
    }
    
    .hour-slot {
        height: 40px;
    }
    
    .time-slot {
        height: 40px;
        font-size: 0.75rem;
    }
}
</style>

<script>
$(document).ready(function() {
    // Navigation des vues
    $('.view-btn').on('click', function() {
        const view = $(this).data('view');
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        loadCalendarView(view);
    });
    
    // Navigation temporelle
    $('.nav-btn').on('click', function() {
        const action = $(this).data('action');
        navigateCalendar(action);
    });
    
    // Filtre booker
    $('#week_booker_filter').on('change', function() {
        const bookerId = $(this).val();
        filterByBooker(bookerId);
    });
    
    // Clic sur une heure disponible
    $('.hour-slot.available').on('click', function() {
        const date = $(this).data('date');
        const hour = $(this).data('hour');
        openQuickBookingModal(date, hour);
    });
    
    // Clic sur une réservation
    $('.reservation-block').on('click', function(e) {
        e.stopPropagation();
        const reservationId = $(this).data('id');
        loadReservationDetails(reservationId);
    });
    
    // Actions sur les réservations
    $('.action-btn').on('click', function() {
        const action = $(this).data('action');
        const reservationId = $('#reservation-details-modal').data('reservation-id');
        performReservationAction(reservationId, action);
    });
    
    // Soumission création rapide
    $('#quick-booking-form').on('submit', function(e) {
        e.preventDefault();
        createQuickBooking();
    });
});

function loadCalendarView(view) {
    // Charger la vue demandée
    window.location.href = updateUrlParameter(window.location.href, 'view', view);
}

function navigateCalendar(action) {
    const currentDate = new Date();
    let newDate;
    
    switch(action) {
        case 'prev':
            newDate = new Date(currentDate.getTime() - (7 * 24 * 60 * 60 * 1000));
            break;
        case 'next':
            newDate = new Date(currentDate.getTime() + (7 * 24 * 60 * 60 * 1000));
            break;
        case 'today':
            newDate = new Date();
            break;
    }
    
    if (newDate) {
        const dateStr = newDate.toISOString().split('T')[0];
        window.location.href = updateUrlParameter(window.location.href, 'date', dateStr);
    }
}

function openQuickBookingModal(date, hour) {
    $('#quick_date').val(date);
    $('#quick_hour_from').val(hour);
    $('#quick_hour_to').val(hour + 1);
    
    const dateObj = new Date(date);
    const dateStr = dateObj.toLocaleDateString('fr-FR') + ' de ' + 
                   hour.toString().padStart(2, '0') + 'h à ' + 
                   (hour + 1).toString().padStart(2, '0') + 'h';
    
    $('.booking-datetime').text(dateStr);
    $('#quick-booking-modal').modal('show');
}

function createQuickBooking() {
    const formData = $('#quick-booking-form').serialize();
    
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: formData + '&action=createQuickBooking',
        success: function(response) {
            if (response.success) {
                $('#quick-booking-modal').modal('hide');
                showNotification('Réservation créée avec succès', 'success');
                location.reload();
            } else {
                showNotification(response.error || 'Erreur lors de la création', 'error');
            }
        },
        error: function() {
            showNotification('Erreur de connexion', 'error');
        }
    });
}

function updateUrlParameter(url, param, paramVal) {
    let newAdditionalURL = "";
    let tempArray = url.split("?");
    let baseURL = tempArray[0];
    let additionalURL = tempArray[1];
    let temp = "";
    
    if (additionalURL) {
        tempArray = additionalURL.split("&");
        for (let i = 0; i < tempArray.length; i++) {
            if (tempArray[i].split('=')[0] != param) {
                newAdditionalURL += temp + tempArray[i];
                temp = "&";
            }
        }
    }
    
    let rows_txt = temp + "" + param + "=" + paramVal;
    return baseURL + "?" + newAdditionalURL + rows_txt;
}
</script>