/**
 * Système de calendrier de réservations pour AdminBookerView
 * Version avec support des réservations multi-jours
 */

// Variables globales pour le calendrier
let calendar = null;
let selectedEvents = [];

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing calendar...');
    initializeReservationCalendar();
    setupEventListeners();
});

/**
 * Initialisation du calendrier FullCalendar
 */
function initializeReservationCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) {
        console.error('Élément #calendar non trouvé dans le DOM');
        return;
    }

    console.log('Initializing FullCalendar...');
    
    // Vérifier que FullCalendar est chargé
    if (typeof FullCalendar === 'undefined') {
        console.error('FullCalendar non chargé');
        return;
    }

    // Vérifier que BookingCalendar est défini
    if (typeof BookingCalendar === 'undefined') {
        console.error('BookingCalendar non défini');
        return;
    }

    const config = BookingCalendar.config;
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek', // Vue semaine par défaut
        locale: config.locale || 'fr',
        height: 'auto',
        businessHours: config.business_hours,
        selectable: true,
        selectMirror: true,
        editable: true,
        eventResizableFromStart: true,
        eventDurationEditable: true,
        
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        
        // Permettre la sélection sur plusieurs jours
        selectOverlap: false,
        selectConstraint: 'businessHours',
        
        // Source des événements via AJAX
        events: function(info, successCallback, failureCallback) {
            loadEvents(info.startStr, info.endStr, successCallback, failureCallback);
        },
        
        // Événements du calendrier
        select: handleDateSelection,
        eventClick: handleEventClick,
        eventDrop: handleEventDrop,
        eventResize: handleEventResize,
        eventDidMount: setupEventTooltip,
        
        loading: function(isLoading) {
            const loadingEl = document.getElementById('calendar-loading');
            if (loadingEl) {
                loadingEl.style.display = isLoading ? 'block' : 'none';
            }
        }
    });

    calendar.render();
    console.log('Calendar rendered successfully');
}

/**
 * Configuration des écouteurs d'événements
 */
function setupEventListeners() {
    console.log('Setting up event listeners...');
    
    // Boutons de vue (IDs corrigés pour correspondre au template)
    const monthBtn = document.getElementById('btn-month-view');
    const weekBtn = document.getElementById('btn-week-view');
    const dayBtn = document.getElementById('btn-day-view');
    
    console.log('View buttons found:', {
        month: !!monthBtn,
        week: !!weekBtn,
        day: !!dayBtn
    });
    
    if (monthBtn) {
        monthBtn.addEventListener('click', function() {
            console.log('Month view clicked');
            calendar.changeView('dayGridMonth');
            updateActiveViewButton('btn-month-view');
        });
    }
    
    if (weekBtn) {
        weekBtn.addEventListener('click', function() {
            console.log('Week view clicked');
            calendar.changeView('timeGridWeek');
            updateActiveViewButton('btn-week-view');
        });
    }
    
    if (dayBtn) {
        dayBtn.addEventListener('click', function() {
            console.log('Day view clicked');
            calendar.changeView('timeGridDay');
            updateActiveViewButton('btn-day-view');
        });
    }
    
    // Bouton actualiser
    const refreshBtn = document.getElementById('btn-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            console.log('Refresh clicked');
            calendar.refetchEvents();
        });
    }
    
    // Bouton nouvelle réservation
    const addBtn = document.getElementById('btn-add-reservation');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            console.log('Add reservation clicked');
            openNewReservationModal();
        });
    }
    
    // Filtres
    const bookerFilter = document.getElementById('booker-filter');
    if (bookerFilter) {
        bookerFilter.addEventListener('change', function() {
            console.log('Booker filter changed:', this.value);
            calendar.refetchEvents();
        });
    }
    
    console.log('Event listeners setup complete');
}

/**
 * Charger les événements via AJAX
 */
function loadEvents(start, end, successCallback, failureCallback) {
    console.log('Loading events for period:', start, 'to', end);
    
    const bookerFilter = document.getElementById('booker-filter');
    const bookerValue = bookerFilter ? bookerFilter.value : '';
    
    const params = new URLSearchParams({
        start: start,
        end: end,
        booker_id: bookerValue || ''
    });
    
    const url = BookingCalendar.ajax_urls.get_events.includes('?') 
        ? BookingCalendar.ajax_urls.get_events + '&' + params.toString()
        : BookingCalendar.ajax_urls.get_events + '?' + params.toString();
    
    console.log('Fetching events from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Events loaded:', data);
            if (Array.isArray(data)) {
                successCallback(data);
            } else if (data.error) {
                console.error('Server error:', data.error);
                failureCallback();
            } else {
                console.error('Invalid data format:', data);
                failureCallback();
            }
        })
        .catch(error => {
            console.error('Error loading events:', error);
            failureCallback();
        });
}

/**
 * Gestion de la sélection de date/heure (avec support multi-jours)
 */
function handleDateSelection(info) {
    console.log('Date selected:', info.start, 'to', info.end);
    
    const startDate = info.start;
    const endDate = info.end;
    
    // Calculer le nombre de jours sélectionnés
    const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
    
    console.log('Days selected:', daysDiff);
    
    if (daysDiff > 1) {
        // Réservation multi-jours
        openNewReservationModal(startDate, endDate, true);
    } else {
        // Réservation sur une seule journée
        openNewReservationModal(startDate, endDate, false);
    }
    
    calendar.unselect();
}

/**
 * Gestion du clic sur un événement
 */
function handleEventClick(info) {
    console.log('Event clicked:', info.event);
    const event = info.event;
    
    // Vérification de la sélection multiple
    if (info.jsEvent.ctrlKey || info.jsEvent.metaKey) {
        toggleEventSelection(event);
        return;
    }
    
    // Afficher les détails dans le modal existant ou créer un simple modal
    showEventDetails(event);
}

/**
 * Afficher les détails d'un événement
 */
function showEventDetails(event) {
    const props = event.extendedProps;
    
    // Calculer la durée de la réservation
    const duration = calculateReservationDuration(event);
    
    // Créer un modal simple pour les détails
    const modalHtml = `
        <div class="modal fade" id="event-details-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                        <h4 class="modal-title">Détails de la réservation</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-sm-6"><strong>Référence:</strong></div>
                            <div class="col-sm-6">${props.booking_reference || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Client:</strong></div>
                            <div class="col-sm-6">${event.title}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Email:</strong></div>
                            <div class="col-sm-6">${props.customer_email || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Téléphone:</strong></div>
                            <div class="col-sm-6">${props.customer_phone || 'N/A'}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Élément:</strong></div>
                            <div class="col-sm-6">${props.booker_name}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Période:</strong></div>
                            <div class="col-sm-6">${formatEventDateTime(event)}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Durée:</strong></div>
                            <div class="col-sm-6">${duration}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Statut:</strong></div>
                            <div class="col-sm-6">${getStatusLabel(props.status)}</div>
                        </div>
                        <div class="row">
                            <div class="col-sm-6"><strong>Prix:</strong></div>
                            <div class="col-sm-6">${props.total_price || '0'}€</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="editReservation('${event.id}')">
                            <i class="icon-edit"></i> Modifier
                        </button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer un modal existant
    const existingModal = document.getElementById('event-details-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter le nouveau modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Afficher le modal
    $('#event-details-modal').modal('show');
}

/**
 * Ouvrir le modal de nouvelle réservation (avec support multi-jours)
 */
function openNewReservationModal(startDate = null, endDate = null, isMultiDay = false) {
    console.log('Opening new reservation modal... Multi-day:', isMultiDay);
    
    // Créer un modal de création avec support multi-jours
    const modalHtml = `
        <div class="modal fade" id="new-reservation-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                        <h4 class="modal-title">Nouvelle réservation ${isMultiDay ? '(Plusieurs jours)' : ''}</h4>
                    </div>
                    <div class="modal-body">
                        <form id="new-reservation-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new-booker">Élément à réserver <span class="text-danger">*</span></label>
                                        <select id="new-booker" name="booker_id" class="form-control" required>
                                            <option value="">Sélectionner...</option>
                                            ${generateBookerOptions()}
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new-status">Statut</label>
                                        <select id="new-status" name="status" class="form-control">
                                            ${generateStatusOptions()}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section dates améliorée -->
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">Période de réservation</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-date-from">Date de début <span class="text-danger">*</span></label>
                                                <input type="date" id="new-date-from" name="date_reserved" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-date-to">Date de fin</label>
                                                <input type="date" id="new-date-to" name="date_to" class="form-control">
                                                <small class="help-block">Laissez vide pour une réservation sur un seul jour</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row" id="hourly-section">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-hour-from">Heure début <span class="text-danger">*</span></label>
                                                <input type="time" id="new-hour-from" name="hour_from" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-hour-to">Heure fin <span class="text-danger">*</span></label>
                                                <input type="time" id="new-hour-to" name="hour_to" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" id="all-day" name="all_day" onchange="toggleHourlySection()">
                                            Réservation toute la journée (pour les réservations multi-jours)
                                        </label>
                                    </div>
                                    
                                    <div class="alert alert-info" id="duration-info" style="display: none;">
                                        <i class="icon-info"></i>
                                        <span id="duration-text"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations client -->
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">Informations client</h4>
                                </div>
                                <div class="panel-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-firstname">Prénom <span class="text-danger">*</span></label>
                                                <input type="text" id="new-firstname" name="customer_firstname" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-lastname">Nom <span class="text-danger">*</span></label>
                                                <input type="text" id="new-lastname" name="customer_lastname" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-email">Email <span class="text-danger">*</span></label>
                                                <input type="email" id="new-email" name="customer_email" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="new-phone">Téléphone</label>
                                                <input type="tel" id="new-phone" name="customer_phone" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new-message">Message</label>
                                        <textarea id="new-message" name="customer_message" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary" onclick="saveNewReservation()">
                            <i class="icon-save"></i> Enregistrer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer un modal existant
    const existingModal = document.getElementById('new-reservation-modal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter le nouveau modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Pré-remplir les dates si fournies
    if (startDate) {
        document.getElementById('new-date-from').value = startDate.toISOString().split('T')[0];
        
        if (isMultiDay && endDate) {
            // Pour les réservations multi-jours, ajuster la date de fin
            const adjustedEndDate = new Date(endDate.getTime() - 24 * 60 * 60 * 1000); // Soustraire 1 jour
            document.getElementById('new-date-to').value = adjustedEndDate.toISOString().split('T')[0];
            document.getElementById('all-day').checked = true;
            toggleHourlySection();
        } else {
            // Réservation d'une journée avec heures
            document.getElementById('new-hour-from').value = startDate.toTimeString().substr(0, 5);
            
            if (endDate) {
                document.getElementById('new-hour-to').value = endDate.toTimeString().substr(0, 5);
            } else {
                // Durée par défaut d'1 heure
                const defaultEndTime = new Date(startDate.getTime() + 60 * 60 * 1000);
                document.getElementById('new-hour-to').value = defaultEndTime.toTimeString().substr(0, 5);
            }
        }
    }
    
    // Configurer les événements pour calculer la durée
    setupDurationCalculation();
    
    // Afficher le modal
    $('#new-reservation-modal').modal('show');
}

/**
 * Basculer l'affichage des heures selon le mode "toute la journée"
 */
function toggleHourlySection() {
    const allDayCheckbox = document.getElementById('all-day');
    const hourlySection = document.getElementById('hourly-section');
    const hourFromInput = document.getElementById('new-hour-from');
    const hourToInput = document.getElementById('new-hour-to');
    
    if (allDayCheckbox.checked) {
        hourlySection.style.display = 'none';
        hourFromInput.value = '00:00';
        hourToInput.value = '23:59';
        hourFromInput.required = false;
        hourToInput.required = false;
    } else {
        hourlySection.style.display = 'block';
        hourFromInput.required = true;
        hourToInput.required = true;
        if (hourFromInput.value === '00:00' && hourToInput.value === '23:59') {
            hourFromInput.value = '10:00';
            hourToInput.value = '11:00';
        }
    }
    
    calculateDuration();
}

/**
 * Configurer le calcul automatique de la durée
 */
function setupDurationCalculation() {
    const inputs = ['new-date-from', 'new-date-to', 'new-hour-from', 'new-hour-to'];
    
    inputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('change', calculateDuration);
        }
    });
    
    document.getElementById('all-day').addEventListener('change', calculateDuration);
    
    // Calcul initial
    calculateDuration();
}

/**
 * Calculer et afficher la durée de la réservation
 */
function calculateDuration() {
    const dateFrom = document.getElementById('new-date-from').value;
    const dateTo = document.getElementById('new-date-to').value;
    const hourFrom = document.getElementById('new-hour-from').value;
    const hourTo = document.getElementById('new-hour-to').value;
    const allDay = document.getElementById('all-day').checked;
    
    const durationInfo = document.getElementById('duration-info');
    const durationText = document.getElementById('duration-text');
    
    if (!dateFrom) {
        durationInfo.style.display = 'none';
        return;
    }
    
    let duration = '';
    
    if (dateTo && dateTo !== dateFrom) {
        // Réservation multi-jours
        const startDate = new Date(dateFrom);
        const endDate = new Date(dateTo);
        const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;
        
        if (allDay) {
            duration = `${daysDiff} jour${daysDiff > 1 ? 's' : ''} complet${daysDiff > 1 ? 's' : ''}`;
        } else {
            duration = `${daysDiff} jour${daysDiff > 1 ? 's' : ''} (${hourFrom} - ${hourTo} chaque jour)`;
        }
    } else {
        // Réservation d'une seule journée
        if (allDay) {
            duration = '1 journée complète';
        } else if (hourFrom && hourTo) {
            const start = new Date(`2000-01-01T${hourFrom}`);
            const end = new Date(`2000-01-01T${hourTo}`);
            const diffMs = end - start;
            const hours = Math.floor(diffMs / (1000 * 60 * 60));
            const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
            
            if (hours > 0 && minutes > 0) {
                duration = `${hours}h${minutes}min`;
            } else if (hours > 0) {
                duration = `${hours}h`;
            } else {
                duration = `${minutes}min`;
            }
        }
    }
    
    if (duration) {
        durationText.textContent = `Durée: ${duration}`;
        durationInfo.style.display = 'block';
    } else {
        durationInfo.style.display = 'none';
    }
}

/**
 * Calculer la durée d'un événement existant
 */
function calculateReservationDuration(event) {
    const start = event.start;
    const end = event.end;
    
    if (!start || !end) return 'N/A';
    
    const startDate = new Date(start);
    const endDate = new Date(end);
    
    // Vérifier si c'est une réservation multi-jours
    const startDay = startDate.toDateString();
    const endDay = endDate.toDateString();
    
    if (startDay !== endDay) {
        const daysDiff = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
        return `${daysDiff} jour${daysDiff > 1 ? 's' : ''}`;
    } else {
        const diffMs = endDate - startDate;
        const hours = Math.floor(diffMs / (1000 * 60 * 60));
        const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        
        if (hours > 0 && minutes > 0) {
            return `${hours}h${minutes}min`;
        } else if (hours > 0) {
            return `${hours}h`;
        } else {
            return `${minutes}min`;
        }
    }
}

/**
 * Générer les options de bookers
 */
function generateBookerOptions() {
    let options = '';
    if (BookingCalendar.bookers && BookingCalendar.bookers.length > 0) {
        BookingCalendar.bookers.forEach(booker => {
            options += `<option value="${booker.id_booker}">${booker.name}</option>`;
        });
    }
    return options;
}

/**
 * Générer les options de statuts
 */
function generateStatusOptions() {
    let options = '';
    if (BookingCalendar.statuses) {
        Object.keys(BookingCalendar.statuses).forEach(key => {
            options += `<option value="${key}">${BookingCalendar.statuses[key]}</option>`;
        });
    }
    return options;
}

/**
 * Sauvegarder une nouvelle réservation
 */
function saveNewReservation() {
    console.log('Saving new reservation...');
    
    // Récupérer les données du formulaire
    const form = document.getElementById('new-reservation-form');
    const formData = new FormData(form);
    
    // Ajouter l'action
    formData.append('action', 'createReservation');
    
    // Convertir en URLSearchParams
    const params = new URLSearchParams();
    for (let [key, value] of formData.entries()) {
        params.append(key, value);
    }
    
    // Récupérer les données pour affichage
    const dateFrom = document.getElementById('new-date-from').value;
    const dateTo = document.getElementById('new-date-to').value;
    const bookerName = document.getElementById('new-booker').selectedOptions[0]?.text || 'N/A';
    const customerName = document.getElementById('new-firstname').value + ' ' + document.getElementById('new-lastname').value;
    
    // Pour l'instant, simuler la création (AJAX pas encore implémenté côté serveur)
    const isMultiDay = dateTo && dateTo !== dateFrom;
    
    alert('Fonctionnalité de création en cours de développement.\n\n' +
          'Type: ' + (isMultiDay ? 'Réservation multi-jours' : 'Réservation simple') + '\n' +
          'Élément: ' + bookerName + '\n' +
          'Date(s): ' + dateFrom + (isMultiDay ? ' au ' + dateTo : '') + '\n' +
          'Client: ' + customerName);
    
    // Fermer le modal
    $('#new-reservation-modal').modal('hide');
}

/**
 * Modifier une réservation
 */
function editReservation(eventId) {
    console.log('Edit reservation:', eventId);
    alert('Fonctionnalité de modification en cours de développement pour l\'événement ID: ' + eventId);
}

/**
 * Fonctions utilitaires
 */
function updateActiveViewButton(activeButtonId) {
    // Retirer la classe active de tous les boutons
    const viewButtons = ['btn-month-view', 'btn-week-view', 'btn-day-view'];
    viewButtons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        if (btn) {
            btn.classList.remove('active');
        }
    });
    
    // Ajouter la classe active au bouton sélectionné
    const activeBtn = document.getElementById(activeButtonId);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}

function formatEventDateTime(event) {
    const start = event.start;
    const end = event.end;
    
    if (!start) return 'N/A';
    
    const startDate = start.toLocaleDateString('fr-FR');
    const endDate = end ? end.toLocaleDateString('fr-FR') : '';
    
    // Vérifier si c'est multi-jours
    if (endDate && startDate !== endDate) {
        return `Du ${startDate} au ${endDate}`;
    } else {
        const startTime = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const endTime = end ? end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
        
        return `${startDate} ${startTime}${endTime ? ' - ' + endTime : ''}`;
    }
}

function getStatusLabel(status) {
    const statuses = BookingCalendar.statuses || {};
    return statuses[status] || 'Inconnu';
}

// Gestion des événements (déplacement, redimensionnement)
function handleEventDrop(info) {
    const event = info.event;
    
    if (confirm('Déplacer cette réservation ?')) {
        console.log('Event dropped:', event.id, 'to', event.start);
        // TODO: Implémenter la mise à jour via AJAX
        alert('Fonctionnalité de déplacement en cours de développement');
    } else {
        info.revert();
    }
}

function handleEventResize(info) {
    const event = info.event;
    
    if (confirm('Modifier la durée de cette réservation ?')) {
        console.log('Event resized:', event.id, 'duration changed');
        // TODO: Implémenter la mise à jour via AJAX
        alert('Fonctionnalité de redimensionnement en cours de développement');
    } else {
        info.revert();
    }
}

function setupEventTooltip(info) {
    const event = info.event;
    const props = event.extendedProps;
    
    info.el.title = `${event.title}
Élément: ${props.booker_name}
Période: ${formatEventDateTime(event)}
Statut: ${getStatusLabel(props.status)}`;
}

// Fonctions placeholders pour compatibilité
function toggleEventSelection(event) {
    console.log('Toggle selection for event:', event.id);
    // TODO: Implémenter la sélection multiple
}

// Exposer des fonctions utiles globalement
window.BookingCalendarManager = {
    refreshEvents: () => {
        if (calendar) {
            calendar.refetchEvents();
        }
    },
    goToDate: (date) => {
        if (calendar) {
            calendar.gotoDate(date);
        }
    },
    changeView: (view) => {
        if (calendar) {
            calendar.changeView(view);
        }
    }
};
