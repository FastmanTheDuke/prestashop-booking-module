/**
 * Gestionnaire du calendrier des disponibilités
 * Gestion complète des créneaux de disponibilité, création en lot et copie
 */

document.addEventListener('DOMContentLoaded', function() {
    
    let calendar;
    let currentModal = null;
    let pendingAction = null;
    let selectedEvents = [];
    
    // Initialisation du calendrier
    initializeCalendar();
    
    // Gestionnaires d'événements
    setupEventHandlers();
    
    /**
     * Initialisation du calendrier FullCalendar
     */
    function initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        if (!calendarEl) {
            console.error('Élément calendrier non trouvé');
            return;
        }
        
        // Vérifier que FullCalendar est chargé
        if (typeof FullCalendar === 'undefined') {
            console.error('FullCalendar non chargé');
            return;
        }
        
        // Vérifier que AvailabilityCalendar est défini
        if (typeof AvailabilityCalendar === 'undefined') {
            console.error('AvailabilityCalendar non défini');
            return;
        }
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: AvailabilityCalendar.config.locale || 'fr',
            initialView: AvailabilityCalendar.config.default_view || 'timeGridWeek',
            initialDate: AvailabilityCalendar.currentDate,
            
            headerToolbar: {
                left: '',
                center: 'title',
                right: ''
            },
            
            height: 'auto',
            
            businessHours: AvailabilityCalendar.config.business_hours,
            
            selectable: true,
            selectMirror: true,
            selectOverlap: false,
            
            eventClick: function(info) {
                handleEventClick(info);
            },
            
            select: function(info) {
                handleDateSelection(info);
            },
            
            dateClick: function(info) {
                handleDateClick(info);
            },
            
            eventDrop: function(info) {
                handleEventDrop(info);
            },
            
            eventResize: function(info) {
                handleEventResize(info);
            },
            
            events: function(info, successCallback, failureCallback) {
                loadAvailabilities(info.startStr, info.endStr, successCallback, failureCallback);
            },
            
            eventDidMount: function(info) {
                setupEventTooltip(info);
                setupEventInteraction(info);
            },
            
            loading: function(isLoading) {
                document.getElementById('calendar-loading').style.display = isLoading ? 'block' : 'none';
                document.getElementById('calendar').style.display = isLoading ? 'none' : 'block';
            }
        });
        
        calendar.render();
    }
    
    /**
     * Configuration des gestionnaires d'événements
     */
    function setupEventHandlers() {
        // Navigation du calendrier
        document.getElementById('prev-btn')?.addEventListener('click', function() {
            calendar.prev();
        });
        
        document.getElementById('next-btn')?.addEventListener('click', function() {
            calendar.next();
        });
        
        document.getElementById('today-btn')?.addEventListener('click', function() {
            calendar.today();
        });
        
        // Sélecteur de vue
        document.querySelectorAll('#view-selector .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const view = this.dataset.view;
                calendar.changeView(view);
                
                // Mettre à jour l'apparence des boutons
                document.querySelectorAll('#view-selector .btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Filtre par booker
        document.getElementById('booker-filter')?.addEventListener('change', function() {
            calendar.refetchEvents();
        });
        
        // Actions en lot
        document.getElementById('bulk-create-btn')?.addEventListener('click', showBulkCreateModal);
        document.getElementById('copy-week-btn')?.addEventListener('click', showCopyWeekModal);
        document.getElementById('recurring-btn')?.addEventListener('click', showRecurringModal);
        document.getElementById('export-btn')?.addEventListener('click', exportAvailabilities);
        
        // Boutons de modal
        document.getElementById('save-availability-btn')?.addEventListener('click', saveAvailability);
        document.getElementById('execute-bulk-create-btn')?.addEventListener('click', executeBulkCreate);
        document.getElementById('execute-copy-week-btn')?.addEventListener('click', executeCopyWeek);
        
        // Gestion des modales
        setupModalHandlers();
    }
    
    /**
     * Charger les disponibilités
     */
    function loadAvailabilities(start, end, successCallback, failureCallback) {
        const bookerId = document.getElementById('booker-filter')?.value || '';
        
        const params = {
            start: start,
            end: end,
            booker_id: bookerId
        };
        
        fetch(AvailabilityCalendar.ajaxUrls.get_availabilities + '&' + new URLSearchParams(params))
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Erreur chargement disponibilités:', data.error);
                    failureCallback(data.error);
                    return;
                }
                successCallback(data);
            })
            .catch(error => {
                console.error('Erreur AJAX:', error);
                failureCallback(error);
            });
    }
    
    /**
     * Gestion du clic sur un événement
     */
    function handleEventClick(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'availability') {
            showAvailabilityDetails(eventProps.availability_id);
        }
    }
    
    /**
     * Gestion de la sélection de dates
     */
    function handleDateSelection(info) {
        showCreateAvailabilityModal(info.start, info.end);
        calendar.unselect();
    }
    
    /**
     * Gestion du clic sur une date
     */
    function handleDateClick(info) {
        const endDate = new Date(info.date);
        endDate.setHours(endDate.getHours() + 1);
        
        showCreateAvailabilityModal(info.date, endDate);
    }
    
    /**
     * Gestion du déplacement d'événement
     */
    function handleEventDrop(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'availability') {
            updateAvailabilityDateTime(eventProps.availability_id, info.event.start, info.event.end);
        }
    }
    
    /**
     * Gestion du redimensionnement d'événement
     */
    function handleEventResize(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'availability') {
            updateAvailabilityDateTime(eventProps.availability_id, info.event.start, info.event.end);
        }
    }
    
    /**
     * Configuration des tooltips pour les événements
     */
    function setupEventTooltip(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'availability') {
            const tooltip = `
                <strong>${eventProps.booker_name}</strong><br>
                Réservations: ${eventProps.current_bookings}/${eventProps.max_bookings}<br>
                Prix: ${eventProps.price_override || eventProps.booker_price}€<br>
                ${eventProps.notes ? 'Notes: ' + eventProps.notes : ''}
            `;
            
            info.el.setAttribute('data-tooltip', tooltip);
            info.el.setAttribute('title', tooltip.replace(/<[^>]*>/g, ''));
        }
    }
    
    /**
     * Configuration des interactions sur les événements
     */
    function setupEventInteraction(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'availability') {
            // Double-clic pour éditer
            info.el.addEventListener('dblclick', function() {
                showEditAvailabilityModal(eventProps.availability_id);
            });
            
            // Clic droit pour menu contextuel
            info.el.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showContextMenu(e, eventProps.availability_id);
            });
        }
    }
    
    /**
     * Afficher les détails d'une disponibilité
     */
    function showAvailabilityDetails(availabilityId) {
        fetch(AvailabilityCalendar.ajaxUrls.get_availability_details + '&id=' + availabilityId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAvailabilityModal(data.data, false);
                } else {
                    showNotification('error', data.message || AvailabilityCalendar.messages.error_loading);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('error', AvailabilityCalendar.messages.error_loading);
            });
    }
    
    /**
     * Afficher la modal de création de disponibilité
     */
    function showCreateAvailabilityModal(startDate, endDate) {
        resetAvailabilityModal();
        
        // Pré-remplir les dates
        if (startDate) {
            const start = new Date(startDate);
            document.getElementById('availability-date-from').value = formatDate(start);
            document.getElementById('availability-time-from').value = formatTime(start);
        }
        
        if (endDate) {
            const end = new Date(endDate);
            document.getElementById('availability-date-to').value = formatDate(end);
            document.getElementById('availability-time-to').value = formatTime(end);
        }
        
        document.getElementById('modal-title').textContent = 'Nouvelle disponibilité';
        showModal('availability-modal');
    }
    
    /**
     * Afficher la modal d'édition de disponibilité
     */
    function showEditAvailabilityModal(availabilityId) {
        fetch(AvailabilityCalendar.ajaxUrls.get_availability_details + '&id=' + availabilityId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAvailabilityModal(data.data, true);
                } else {
                    showNotification('error', data.message || AvailabilityCalendar.messages.error_loading);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('error', AvailabilityCalendar.messages.error_loading);
            });
    }
    
    /**
     * Afficher la modal de disponibilité
     */
    function showAvailabilityModal(availability, isEdit) {
        resetAvailabilityModal();
        
        if (isEdit) {
            document.getElementById('availability-id').value = availability.id;
            document.getElementById('modal-title').textContent = 'Modifier la disponibilité';
        } else {
            document.getElementById('modal-title').textContent = 'Détails de la disponibilité';
        }
        
        // Remplir les champs
        document.getElementById('availability-booker').value = availability.id_booker;
        document.getElementById('availability-max-bookings').value = availability.max_bookings;
        document.getElementById('availability-date-from').value = formatDate(new Date(availability.date_from));
        document.getElementById('availability-date-to').value = formatDate(new Date(availability.date_to));
        document.getElementById('availability-time-from').value = availability.time_from.substring(0, 5);
        document.getElementById('availability-time-to').value = availability.time_to.substring(0, 5);
        document.getElementById('availability-price-override').value = availability.price_override || '';
        document.getElementById('availability-active').value = availability.active;
        document.getElementById('availability-notes').value = availability.notes || '';
        document.getElementById('availability-recurring').value = availability.recurring || 0;
        document.getElementById('availability-recurring-type').value = availability.recurring_type || '';
        document.getElementById('availability-recurring-end').value = availability.recurring_end || '';
        
        showModal('availability-modal');
    }
    
    /**
     * Sauvegarder une disponibilité
     */
    function saveAvailability() {
        const formData = new FormData(document.getElementById('availability-form'));
        
        // Validation côté client
        if (!validateAvailabilityForm()) {
            return;
        }
        
        const params = new URLSearchParams();
        for (let [key, value] of formData.entries()) {
            params.append(key, value);
        }
        
        fetch(AvailabilityCalendar.ajaxUrls.save_availability, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || AvailabilityCalendar.messages.success_save);
                hideModal('availability-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la sauvegarde');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la sauvegarde');
        });
    }
    
    /**
     * Validation du formulaire de disponibilité
     */
    function validateAvailabilityForm() {
        const requiredFields = ['availability-booker', 'availability-date-from', 'availability-date-to', 'availability-time-from', 'availability-time-to'];
        
        for (let fieldId of requiredFields) {
            const field = document.getElementById(fieldId);
            if (!field || !field.value.trim()) {
                showNotification('error', AvailabilityCalendar.messages.validation_required);
                field?.focus();
                return false;
            }
        }
        
        // Validation des dates
        const dateFrom = new Date(document.getElementById('availability-date-from').value);
        const dateTo = new Date(document.getElementById('availability-date-to').value);
        
        if (dateTo < dateFrom) {
            showNotification('error', AvailabilityCalendar.messages.validation_date_range);
            return false;
        }
        
        // Validation des heures
        const timeFrom = document.getElementById('availability-time-from').value;
        const timeTo = document.getElementById('availability-time-to').value;
        
        if (timeTo <= timeFrom) {
            showNotification('error', AvailabilityCalendar.messages.validation_time_range);
            return false;
        }
        
        return true;
    }
    
    /**
     * Mettre à jour la date/heure d'une disponibilité (drag & drop)
     */
    function updateAvailabilityDateTime(availabilityId, startDate, endDate) {
        const params = new URLSearchParams({
            id: availabilityId,
            date_from: formatDate(startDate),
            date_to: formatDate(endDate),
            time_from: formatTime(startDate),
            time_to: formatTime(endDate)
        });
        
        fetch(AvailabilityCalendar.ajaxUrls.update_availability, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Disponibilité mise à jour');
            } else {
                showNotification('error', data.message || 'Erreur lors de la mise à jour');
                calendar.refetchEvents(); // Revert changes
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            calendar.refetchEvents(); // Revert changes
        });
    }
    
    /**
     * Afficher la modal de création en lot
     */
    function showBulkCreateModal() {
        showModal('bulk-create-modal');
    }
    
    /**
     * Exécuter la création en lot
     */
    function executeBulkCreate() {
        const formData = new FormData(document.getElementById('bulk-create-form'));
        
        // Récupérer les jours sélectionnés
        const selectedDays = [];
        document.querySelectorAll('input[name="days[]"]:checked').forEach(checkbox => {
            selectedDays.push(checkbox.value);
        });
        
        if (selectedDays.length === 0) {
            showNotification('error', 'Veuillez sélectionner au moins un jour');
            return;
        }
        
        const params = new URLSearchParams();
        for (let [key, value] of formData.entries()) {
            if (key !== 'days[]') {
                params.append(key, value);
            }
        }
        selectedDays.forEach(day => params.append('days[]', day));
        
        fetch(AvailabilityCalendar.ajaxUrls.bulk_create, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || AvailabilityCalendar.messages.success_bulk_create);
                hideModal('bulk-create-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la création en lot');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la création en lot');
        });
    }
    
    /**
     * Afficher la modal de copie de semaine
     */
    function showCopyWeekModal() {
        // Générer les options de semaines
        generateWeekOptions();
        showModal('copy-week-modal');
    }
    
    /**
     * Générer les options de semaines pour la copie
     */
    function generateWeekOptions() {
        const select = document.getElementById('copy-target-weeks');
        if (!select) return;
        
        select.innerHTML = '';
        
        const today = new Date();
        for (let i = 1; i <= 26; i++) { // 26 semaines à venir
            const date = new Date(today);
            date.setDate(date.getDate() + (i * 7));
            
            const year = date.getFullYear();
            const week = getWeekNumber(date);
            const weekValue = year + '-W' + (week < 10 ? '0' : '') + week;
            const weekLabel = 'Semaine ' + week + ' (' + formatDateRange(date) + ')';
            
            const option = document.createElement('option');
            option.value = weekValue;
            option.textContent = weekLabel;
            select.appendChild(option);
        }
    }
    
    /**
     * Exécuter la copie de semaine
     */
    function executeCopyWeek() {
        const sourceWeek = document.getElementById('copy-source-week').value;
        const targetWeeks = Array.from(document.getElementById('copy-target-weeks').selectedOptions).map(opt => opt.value);
        
        if (!sourceWeek || targetWeeks.length === 0) {
            showNotification('error', 'Veuillez sélectionner une semaine source et au moins une semaine de destination');
            return;
        }
        
        const params = new URLSearchParams({
            source_week: sourceWeek
        });
        
        targetWeeks.forEach(week => params.append('target_weeks[]', week));
        
        fetch(AvailabilityCalendar.ajaxUrls.copy_week, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || AvailabilityCalendar.messages.success_copy_week);
                hideModal('copy-week-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la copie');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la copie');
        });
    }
    
    /**
     * Exporter les disponibilités
     */
    function exportAvailabilities() {
        const currentView = calendar.view;
        const start = formatDate(currentView.activeStart);
        const end = formatDate(currentView.activeEnd);
        
        const params = new URLSearchParams({
            start: start,
            end: end,
            format: 'csv'
        });
        
        const url = AvailabilityCalendar.ajaxUrls.export_availabilities + '&' + params.toString();
        window.location.href = url;
    }
    
    /**
     * Afficher un menu contextuel
     */
    function showContextMenu(event, availabilityId) {
        // Créer le menu contextuel
        const menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.style.position = 'fixed';
        menu.style.left = event.pageX + 'px';
        menu.style.top = event.pageY + 'px';
        menu.style.zIndex = '9999';
        menu.style.background = 'white';
        menu.style.border = '1px solid #ccc';
        menu.style.borderRadius = '4px';
        menu.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        
        const actions = [
            { label: 'Modifier', action: () => showEditAvailabilityModal(availabilityId) },
            { label: 'Supprimer', action: () => deleteAvailability(availabilityId) },
            { label: 'Dupliquer', action: () => duplicateAvailability(availabilityId) }
        ];
        
        actions.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.textContent = item.label;
            menuItem.style.padding = '8px 16px';
            menuItem.style.cursor = 'pointer';
            menuItem.addEventListener('click', () => {
                item.action();
                document.body.removeChild(menu);
            });
            menuItem.addEventListener('mouseenter', () => {
                menuItem.style.backgroundColor = '#f5f5f5';
            });
            menuItem.addEventListener('mouseleave', () => {
                menuItem.style.backgroundColor = 'white';
            });
            menu.appendChild(menuItem);
        });
        
        document.body.appendChild(menu);
        
        // Supprimer le menu si on clique ailleurs
        const removeMenu = (e) => {
            if (!menu.contains(e.target)) {
                document.body.removeChild(menu);
                document.removeEventListener('click', removeMenu);
            }
        };
        setTimeout(() => document.addEventListener('click', removeMenu), 100);
    }
    
    /**
     * Supprimer une disponibilité
     */
    function deleteAvailability(availabilityId) {
        if (!confirm(AvailabilityCalendar.messages.confirm_delete)) {
            return;
        }
        
        const params = new URLSearchParams({ id: availabilityId });
        
        fetch(AvailabilityCalendar.ajaxUrls.delete_availability, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || AvailabilityCalendar.messages.success_delete);
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la suppression');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la suppression');
        });
    }
    
    /**
     * Configuration des modales
     */
    function setupModalHandlers() {
        // Fermeture des modales avec échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && currentModal) {
                hideModal(currentModal);
            }
        });
        
        // Fermeture des modales en cliquant sur le fond
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideModal(this.id);
                }
            });
        });
    }
    
    /**
     * Fonctions utilitaires
     */
    
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            modal.classList.add('show');
            currentModal = modalId;
        }
    }
    
    function hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            currentModal = null;
        }
    }
    
    function resetAvailabilityModal() {
        document.getElementById('availability-form').reset();
        document.getElementById('availability-id').value = '';
    }
    
    function showNotification(type, message) {
        // Créer la notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '10000';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        // Suppression automatique après 5 secondes
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    function formatDate(date) {
        const d = new Date(date);
        const month = (d.getMonth() + 1).toString().padStart(2, '0');
        const day = d.getDate().toString().padStart(2, '0');
        return d.getFullYear() + '-' + month + '-' + day;
    }
    
    function formatTime(date) {
        const d = new Date(date);
        const hours = d.getHours().toString().padStart(2, '0');
        const minutes = d.getMinutes().toString().padStart(2, '0');
        return hours + ':' + minutes;
    }
    
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    
    function formatDateRange(date) {
        const monday = new Date(date);
        monday.setDate(date.getDate() - date.getDay() + 1);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        
        return formatDate(monday) + ' au ' + formatDate(sunday);
    }
    
    function showRecurringModal() {
        // À implémenter pour les créneaux récurrents
        alert('Fonction de récurrence à implémenter');
    }
    
    function duplicateAvailability(availabilityId) {
        // À implémenter pour la duplication
        alert('Fonction de duplication à implémenter');
    }
});
