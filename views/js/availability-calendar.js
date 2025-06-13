/**
 * Calendrier des Disponibilités - Interface Interactive
 */
$(document).ready(function() {
    
    // Initialisation
    AvailabilityCalendar.init();
    
    // Events handlers
    setupEventHandlers();
    
    // Charger le calendrier initial
    loadCalendar();
});

/**
 * Objet principal du calendrier des disponibilités
 */
AvailabilityCalendar.init = function() {
    this.currentBookerId = $('#booker-select').val() || '';
    this.currentView = $('#calendar-view').val() || 'month';
    this.selectionMode = 'single';
    this.selectedDates = [];
    this.calendarData = null;
    
    // Mettre à jour l'affichage de la période
    this.updatePeriodDisplay();
};

/**
 * Configuration des gestionnaires d'événements
 */
function setupEventHandlers() {
    
    // Sélection du booker
    $('#booker-select').on('change', function() {
        AvailabilityCalendar.currentBookerId = $(this).val();
        loadCalendar();
    });
    
    // Changement de vue
    $('#calendar-view').on('change', function() {
        AvailabilityCalendar.currentView = $(this).val();
        AvailabilityCalendar.updatePeriodDisplay();
        loadCalendar();
    });
    
    // Navigation
    $('#prev-period').on('click', function() {
        navigatePeriod(-1);
    });
    
    $('#next-period').on('click', function() {
        navigatePeriod(1);
    });
    
    $('#today-btn').on('click', function() {
        goToToday();
    });
    
    $('#refresh-calendar').on('click', function() {
        loadCalendar();
    });
    
    // Mode de sélection
    $('#select-mode, #multi-select-mode').on('click', function() {
        var mode = $(this).data('mode');
        setSelectionMode(mode);
    });
    
    // Actions en lot
    $('#bulk-add-availability').on('click', function() {
        if (AvailabilityCalendar.selectedDates.length === 0) {
            alert('Veuillez sélectionner au moins une date.');
            return;
        }
        showBulkAvailabilityModal('add');
    });
    
    $('#bulk-remove-availability').on('click', function() {
        if (AvailabilityCalendar.selectedDates.length === 0) {
            alert('Veuillez sélectionner au moins une date.');
            return;
        }
        showBulkAvailabilityModal('remove');
    });
    
    // Modal disponibilité simple
    $('#save-availability').on('click', function() {
        saveAvailability();
    });
    
    // Récurrence
    $('#availability-recurring').on('change', function() {
        $('#recurring-options').toggle(this.checked);
    });
    
    // Modal disponibilité en lot
    $('#confirm-bulk-action').on('click', function() {
        processBulkAvailability();
    });
}

/**
 * Charger les données du calendrier
 */
function loadCalendar() {
    $('#calendar-loading').show();
    $('#calendar-content').hide();
    
    var params = {
        action: 'loadCalendar',
        booker_id: AvailabilityCalendar.currentBookerId,
        year: AvailabilityCalendar.currentYear,
        month: AvailabilityCalendar.currentMonth,
        view: AvailabilityCalendar.currentView
    };
    
    $.ajax({
        url: AvailabilityCalendar.ajaxUrl,
        type: 'POST',
        data: params,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                AvailabilityCalendar.calendarData = response.data;
                renderCalendar(response.data);
            } else {
                alert('Erreur lors du chargement du calendrier: ' + (response.error || 'Erreur inconnue'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX:', error);
            alert('Erreur de communication avec le serveur.');
        },
        complete: function() {
            $('#calendar-loading').hide();
            $('#calendar-content').show();
        }
    });
}

/**
 * Rendu du calendrier selon la vue
 */
function renderCalendar(data) {
    var html = '';
    
    switch (AvailabilityCalendar.currentView) {
        case 'month':
            html = renderMonthView(data);
            break;
        case 'week':
            html = renderWeekView(data);
            break;
        case 'day':
            html = renderDayView(data);
            break;
    }
    
    $('#calendar-content').html(html);
    
    // Attacher les événements aux jours du calendrier
    attachCalendarEvents();
}

/**
 * Rendu de la vue mensuelle
 */
function renderMonthView(data) {
    var monthInfo = data.month_info;
    var availabilities = data.availabilities || [];
    var reservations = data.reservations || [];
    
    // Créer un index des disponibilités par date
    var availabilityIndex = {};
    availabilities.forEach(function(avail) {
        var startDate = new Date(avail.date_from);
        var endDate = new Date(avail.date_to);
        
        for (var d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            var dateStr = formatDate(d);
            if (!availabilityIndex[dateStr]) {
                availabilityIndex[dateStr] = [];
            }
            availabilityIndex[dateStr].push(avail);
        }
    });
    
    // Créer un index des réservations par date
    var reservationIndex = {};
    reservations.forEach(function(res) {
        var dateStr = res.date_reserved;
        if (!reservationIndex[dateStr]) {
            reservationIndex[dateStr] = [];
        }
        reservationIndex[dateStr].push(res);
    });
    
    var html = '<div class="calendar-month">';
    html += '<table class="table table-bordered calendar-table">';
    
    // En-tête des jours de la semaine
    html += '<thead><tr>';
    var dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    dayNames.forEach(function(day) {
        html += '<th class="text-center">' + day + '</th>';
    });
    html += '</tr></thead>';
    
    html += '<tbody>';
    
    // Calculer le premier et dernier jour à afficher
    var firstDay = new Date(monthInfo.year, monthInfo.month - 1, 1);
    var lastDay = new Date(monthInfo.year, monthInfo.month - 1, monthInfo.days_in_month);
    
    // Ajuster pour commencer le lundi
    var startCalendar = new Date(firstDay);
    startCalendar.setDate(startCalendar.getDate() - (firstDay.getDay() + 6) % 7);
    
    var currentDate = new Date(startCalendar);
    
    // Générer les semaines
    for (var week = 0; week < 6; week++) {
        html += '<tr>';
        
        for (var day = 0; day < 7; day++) {
            var dateStr = formatDate(currentDate);
            var isCurrentMonth = currentDate.getMonth() === monthInfo.month - 1;
            var isToday = isDateToday(currentDate);
            var isSelected = AvailabilityCalendar.selectedDates.includes(dateStr);
            
            var dayAvailabilities = availabilityIndex[dateStr] || [];
            var dayReservations = reservationIndex[dateStr] || [];
            
            var cssClasses = ['calendar-day'];
            if (!isCurrentMonth) cssClasses.push('other-month');
            if (isToday) cssClasses.push('today');
            if (isSelected) cssClasses.push('selected');
            if (dayAvailabilities.length > 0) cssClasses.push('has-availability');
            if (dayReservations.length > 0) cssClasses.push('has-reservations');
            
            html += '<td class="' + cssClasses.join(' ') + '" data-date="' + dateStr + '">';
            html += '<div class="day-number">' + currentDate.getDate() + '</div>';
            
            if (dayAvailabilities.length > 0) {
                html += '<div class="day-availability-count">';
                html += '<i class="icon-calendar-check-o"></i> ' + dayAvailabilities.length + ' dispo.';
                html += '</div>';
            }
            
            if (dayReservations.length > 0) {
                html += '<div class="day-reservation-count">';
                html += '<i class="icon-user"></i> ' + dayReservations.length + ' rés.';
                html += '</div>';
            }
            
            html += '</td>';
            
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        html += '</tr>';
        
        // Arrêter si on a dépassé le mois et qu'on a au moins une ligne du mois suivant
        if (currentDate.getMonth() !== monthInfo.month - 1 && week >= 4) {
            break;
        }
    }
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    return html;
}

/**
 * Attacher les événements aux éléments du calendrier
 */
function attachCalendarEvents() {
    $('.calendar-day').on('click', function(e) {
        e.preventDefault();
        var date = $(this).data('date');
        
        if (AvailabilityCalendar.selectionMode === 'single') {
            // Mode simple : ouvrir la modal de création
            if (!AvailabilityCalendar.currentBookerId) {
                alert('Veuillez sélectionner un élément à réserver.');
                return;
            }
            showAvailabilityModal(date);
        } else {
            // Mode multiple : sélectionner/désélectionner la date
            toggleDateSelection(date);
        }
    });
    
    // Double-clic pour voir les détails
    $('.calendar-day').on('dblclick', function(e) {
        e.preventDefault();
        var date = $(this).data('date');
        showDayDetails(date);
    });
}

/**
 * Basculer la sélection d'une date
 */
function toggleDateSelection(date) {
    var index = AvailabilityCalendar.selectedDates.indexOf(date);
    
    if (index === -1) {
        AvailabilityCalendar.selectedDates.push(date);
        $('[data-date="' + date + '"]').addClass('selected');
    } else {
        AvailabilityCalendar.selectedDates.splice(index, 1);
        $('[data-date="' + date + '"]').removeClass('selected');
    }
    
    updateSelectionDisplay();
}

/**
 * Définir le mode de sélection
 */
function setSelectionMode(mode) {
    AvailabilityCalendar.selectionMode = mode;
    
    // Mettre à jour l'interface
    $('#select-mode, #multi-select-mode').removeClass('active');
    $('[data-mode="' + mode + '"]').addClass('active');
    
    // Afficher/masquer les actions en lot
    if (mode === 'multi') {
        $('#bulk-actions').show();
        $('#calendar-content').addClass('multi-select-mode');
    } else {
        $('#bulk-actions').hide();
        $('#calendar-content').removeClass('multi-select-mode');
    }
    
    // Réinitialiser la sélection
    AvailabilityCalendar.selectedDates = [];
    $('.calendar-day').removeClass('selected');
    updateSelectionDisplay();
}

/**
 * Mettre à jour l'affichage de la sélection
 */
function updateSelectionDisplay() {
    var count = AvailabilityCalendar.selectedDates.length;
    $('#selected-dates-count').text(count);
    
    // Activer/désactiver les boutons d'action
    $('#bulk-add-availability, #bulk-remove-availability').prop('disabled', count === 0);
}

/**
 * Afficher la modal de création de disponibilité
 */
function showAvailabilityModal(date) {
    $('#availability-booker-id').val(AvailabilityCalendar.currentBookerId);
    
    if (date) {
        $('#availability-date-from').val(date + 'T08:00');
        $('#availability-date-to').val(date + 'T18:00');
    }
    
    $('#availability-modal').modal('show');
}

/**
 * Afficher la modal d'action en lot
 */
function showBulkAvailabilityModal(action) {
    $('#bulk-booker-id').val(AvailabilityCalendar.currentBookerId);
    $('#bulk-action').val(action);
    
    if (action === 'add') {
        $('#bulk-add-options').show();
        $('#bulk-remove-options').hide();
        $('.modal-title').text('Ajouter des disponibilités');
        $('#confirm-bulk-action').removeClass('btn-danger').addClass('btn-success').text('Ajouter');
    } else {
        $('#bulk-add-options').hide();
        $('#bulk-remove-options').show();
        $('.modal-title').text('Supprimer des disponibilités');
        $('#confirm-bulk-action').removeClass('btn-success').addClass('btn-danger').text('Supprimer');
    }
    
    $('#selected-dates-count').text(AvailabilityCalendar.selectedDates.length);
    $('#bulk-availability-modal').modal('show');
}

/**
 * Sauvegarder une disponibilité
 */
function saveAvailability() {
    var formData = {
        action: 'saveAvailability',
        booker_id: $('#availability-booker-id').val(),
        date_from: $('#availability-date-from').val(),
        date_to: $('#availability-date-to').val(),
        recurring: $('#availability-recurring').is(':checked'),
        recurring_pattern: $('#recurring-pattern').val()
    };
    
    $.ajax({
        url: AvailabilityCalendar.ajaxUrl,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#availability-modal').modal('hide');
                loadCalendar();
                alert('Disponibilité créée avec succès.');
            } else {
                alert('Erreur: ' + (response.error || 'Erreur inconnue'));
            }
        },
        error: function() {
            alert('Erreur de communication avec le serveur.');
        }
    });
}

/**
 * Traiter l'action en lot
 */
function processBulkAvailability() {
    var formData = {
        action: 'bulkAvailability',
        booker_id: $('#bulk-booker-id').val(),
        action: $('#bulk-action').val(),
        dates: AvailabilityCalendar.selectedDates,
        time_from: $('#bulk-time-from').val(),
        time_to: $('#bulk-time-to').val()
    };
    
    $.ajax({
        url: AvailabilityCalendar.ajaxUrl,
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            $('#bulk-availability-modal').modal('hide');
            
            if (response.success) {
                alert('Action réalisée avec succès sur ' + response.success_count + ' date(s).');
                loadCalendar();
            } else {
                var message = 'Action partiellement réalisée:\n';
                message += '- Succès: ' + response.success_count + '\n';
                message += '- Erreurs: ' + response.error_count + '\n';
                if (response.errors.length > 0) {
                    message += 'Détails:\n' + response.errors.join('\n');
                }
                alert(message);
                loadCalendar();
            }
            
            // Réinitialiser la sélection
            AvailabilityCalendar.selectedDates = [];
            updateSelectionDisplay();
        },
        error: function() {
            alert('Erreur de communication avec le serveur.');
        }
    });
}

/**
 * Navigation dans les périodes
 */
function navigatePeriod(direction) {
    switch (AvailabilityCalendar.currentView) {
        case 'month':
            AvailabilityCalendar.currentMonth += direction;
            if (AvailabilityCalendar.currentMonth > 12) {
                AvailabilityCalendar.currentMonth = 1;
                AvailabilityCalendar.currentYear++;
            } else if (AvailabilityCalendar.currentMonth < 1) {
                AvailabilityCalendar.currentMonth = 12;
                AvailabilityCalendar.currentYear--;
            }
            break;
        case 'week':
            // À implémenter pour la vue semaine
            break;
        case 'day':
            // À implémenter pour la vue jour
            break;
    }
    
    AvailabilityCalendar.updatePeriodDisplay();
    loadCalendar();
}

/**
 * Aller à aujourd'hui
 */
function goToToday() {
    var today = new Date();
    AvailabilityCalendar.currentYear = today.getFullYear();
    AvailabilityCalendar.currentMonth = today.getMonth() + 1;
    
    AvailabilityCalendar.updatePeriodDisplay();
    loadCalendar();
}

/**
 * Mettre à jour l'affichage de la période
 */
AvailabilityCalendar.updatePeriodDisplay = function() {
    var display = '';
    
    switch (this.currentView) {
        case 'month':
            var monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
            display = monthNames[this.currentMonth - 1] + ' ' + this.currentYear;
            break;
        case 'week':
            display = 'Semaine - ' + this.currentYear;
            break;
        case 'day':
            display = 'Jour - ' + this.currentYear;
            break;
    }
    
    $('#current-period').val(display);
};

/**
 * Fonctions utilitaires
 */
function formatDate(date) {
    return date.getFullYear() + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' +
           String(date.getDate()).padStart(2, '0');
}

function isDateToday(date) {
    var today = new Date();
    return date.getDate() === today.getDate() &&
           date.getMonth() === today.getMonth() &&
           date.getFullYear() === today.getFullYear();
}