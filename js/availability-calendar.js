/**
 * Gestionnaire du calendrier des disponibilités
 * Gestion complète des créneaux de disponibilité, création en lot et copie
 */

document.addEventListener('DOMContentLoaded', function() {
    
    let calendar;
    let currentModal = null;
    let pendingAction = null;
    
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
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: AvailabilityCalendar.config.locale || 'fr',
            initialView: AvailabilityCalendar.config.default_view || 'dayGridMonth',
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
            
            events: function(info, successCallback, failureCallback) {
                loadAvailabilities(info.startStr, info.endStr, successCallback, failureCallback);
            },
            
            eventDidMount: function(info) {
                setupEventTooltip(info);
                setupEventInteraction(info);
            }
        });
        
        calendar.render();
    }
    
    /**
     * Configuration des gestionnaires d'événements
     */
    function setupEventHandlers() {
        // Navigation du calendrier
        document.getElementById('today-btn')?.addEventListener('click', () => {
            calendar.today();
        });
        
        document.getElementById('prev-btn')?.addEventListener('click', () => {
            calendar.prev();
        });
        
        document.getElementById('next-btn')?.addEventListener('click', () => {
            calendar.next();
        });
        
        // Changement de vue
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const view = e.target.getAttribute('data-view');
                calendar.changeView(view);
                
                // Mettre à jour les boutons actifs
                document.querySelectorAll('[data-view]').forEach(b => b.classList.remove('btn-info'));
                e.target.classList.add('btn-info');
            });
        });
        
        // Filtres
        document.getElementById('booker-filter')?.addEventListener('change', () => {
            refreshCalendar();
        });
        
        // Actions principales
        document.getElementById('new-availability-btn')?.addEventListener('click', () => {
            openAvailabilityModal();
        });
        
        document.getElementById('bulk-create-btn')?.addEventListener('click', () => {
            openBulkCreateModal();
        });
        
        document.getElementById('copy-week-btn')?.addEventListener('click', () => {
            openCopyWeekModal();
        });
        
        // Modal de disponibilité
        document.getElementById('save-availability-btn')?.addEventListener('click', () => {
            saveAvailability();
        });
        
        document.getElementById('delete-availability-btn')?.addEventListener('click', () => {
            deleteAvailability();
        });
        
        // Modal de création en lot
        document.getElementById('execute-bulk-create-btn')?.addEventListener('click', () => {
            executeBulkCreate();
        });
        
        // Boutons de sélection rapide des jours
        document.getElementById('select-weekdays')?.addEventListener('click', () => {
            selectDays([1, 2, 3, 4, 5]);
        });
        
        document.getElementById('select-weekend')?.addEventListener('click', () => {
            selectDays([6, 0]);
        });
        
        document.getElementById('select-all-days')?.addEventListener('click', () => {
            selectDays([0, 1, 2, 3, 4, 5, 6]);
        });
        
        document.getElementById('clear-days')?.addEventListener('click', () => {
            selectDays([]);
        });
        
        // Modal de copie de semaine
        document.getElementById('execute-copy-week-btn')?.addEventListener('click', () => {
            executeCopyWeek();
        });
        
        // Mode sélection
        document.getElementById('apply-bulk-create')?.addEventListener('click', () => {
            applyBulkSelection();
        });
        
        document.getElementById('cancel-selection')?.addEventListener('click', () => {
            exitSelectionMode();
        });
        
        // Modal de confirmation
        document.getElementById('confirm-action-btn')?.addEventListener('click', () => {
            if (pendingAction) {
                pendingAction();
                $('#confirm-modal').modal('hide');
                pendingAction = null;
            }
        });
        
        // Validation des dates dans le modal
        document.getElementById('modal-date-from')?.addEventListener('change', validateDateRange);
        document.getElementById('modal-date-to')?.addEventListener('change', validateDateRange);
        
        // Preset times
        document.getElementById('preset-times')?.addEventListener('change', (e) => {
            applyPresetTime(e.target.value);
        });
    }
    
    /**
     * Charger les disponibilités du calendrier
     */
    function loadAvailabilities(start, end, successCallback, failureCallback) {
        const bookerFilter = document.getElementById('booker-filter')?.value || 'all';
        
        const params = new URLSearchParams({
            start: start,
            end: end,
            booker_id: bookerFilter
        });
        
        fetch(`${AvailabilityCalendar.ajaxUrls.get_availabilities}&${params}`)
            .then(response => response.json())
            .then(data => {
                if (Array.isArray(data)) {
                    successCallback(data);
                } else {
                    console.error('Format de données invalide:', data);
                    failureCallback();
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des disponibilités:', error);
                failureCallback();
                showNotification(AvailabilityCalendar.messages.error, 'error');
            });
    }
    
    /**
     * Gestion du clic sur un événement
     */
    function handleEventClick(info) {
        const event = info.event;
        
        if (AvailabilityCalendar.selectionMode) {
            // En mode sélection, gérer la sélection de dates
            toggleDateSelection(event.startStr.split('T')[0]);
            return;
        }
        
        // Sinon, ouvrir le modal d'édition
        openAvailabilityModal(event);
    }
    
    /**
     * Gestion de la sélection de dates
     */
    function handleDateSelection(info) {
        if (AvailabilityCalendar.selectionMode) {
            // Ajouter toutes les dates de la sélection
            let current = new Date(info.start);
            const end = new Date(info.end);
            
            while (current < end) {
                const dateStr = current.toISOString().split('T')[0];
                toggleDateSelection(dateStr);
                current.setDate(current.getDate() + 1);
            }
        } else {
            // Mode normal - ouvrir le modal avec les dates préremplies
            openAvailabilityModal(null, info);
        }
    }
    
    /**
     * Gestion du clic sur une date
     */
    function handleDateClick(info) {
        if (AvailabilityCalendar.selectionMode) {
            toggleDateSelection(info.dateStr);
        }
    }
    
    /**
     * Gestion du déplacement d'événement
     */
    function handleEventDrop(info) {
        const event = info.event;
        const props = event.extendedProps;
        
        // Calculer les nouvelles dates
        const daysDiff = Math.round((info.event.start - info.oldEvent.start) / (1000 * 60 * 60 * 24));
        const newDateFrom = new Date(new Date(props.date_from).getTime() + daysDiff * 24 * 60 * 60 * 1000);
        const newDateTo = new Date(new Date(props.date_to).getTime() + daysDiff * 24 * 60 * 60 * 1000);
        
        updateAvailabilityDates(
            props.availability_id,
            newDateFrom.toISOString().split('T')[0],
            newDateTo.toISOString().split('T')[0]
        );
    }
    
    /**
     * Configuration du tooltip pour un événement
     */
    function setupEventTooltip(info) {
        const event = info.event;
        const props = event.extendedProps;
        
        let tooltipContent = `
            <strong>${props.booker_name}</strong><br>
            ${props.date_from} → ${props.date_to}
        `;
        
        if (props.reservations_count > 0) {
            tooltipContent += `<br>📅 ${props.reservations_count} réservation(s)`;
        }
        
        if (props.price) {
            tooltipContent += `<br>💰 ${props.price}€`;
        }
        
        info.el.setAttribute('title', tooltipContent.replace(/<br>/g, '\n').replace(/<[^>]*>/g, ''));
        info.el.setAttribute('data-toggle', 'tooltip');
        info.el.setAttribute('data-html', 'true');
        info.el.setAttribute('data-placement', 'top');
    }
    
    /**
     * Configuration de l'interaction pour un événement
     */
    function setupEventInteraction(info) {
        const event = info.event;
        
        // Ajouter des classes CSS selon le contexte
        if (event.extendedProps.reservations_count > 0) {
            info.el.classList.add('fc-event-with-reservations');
        }
        
        // Gestion du mode sélection
        if (AvailabilityCalendar.selectionMode) {
            info.el.style.cursor = 'pointer';
        }
    }
    
    /**
     * Basculer la sélection d'une date
     */
    function toggleDateSelection(dateStr) {
        const index = AvailabilityCalendar.selectedDates.indexOf(dateStr);
        
        if (index > -1) {
            AvailabilityCalendar.selectedDates.splice(index, 1);
        } else {
            AvailabilityCalendar.selectedDates.push(dateStr);
        }
        
        updateSelectionUI();
        highlightSelectedDates();
    }
    
    /**
     * Mettre à jour l'interface de sélection
     */
    function updateSelectionUI() {
        const count = AvailabilityCalendar.selectedDates.length;
        document.getElementById('selected-dates-count').textContent = count;
        
        const applyBtn = document.getElementById('apply-bulk-create');
        if (applyBtn) {
            applyBtn.disabled = count === 0;
        }
    }
    
    /**
     * Surligner les dates sélectionnées
     */
    function highlightSelectedDates() {
        // Retirer toutes les sélections existantes
        document.querySelectorAll('.fc-day.fc-selected').forEach(el => {
            el.classList.remove('fc-selected');
        });
        
        // Ajouter la sélection aux nouvelles dates
        AvailabilityCalendar.selectedDates.forEach(dateStr => {
            const dayEl = document.querySelector(`[data-date="${dateStr}"]`);
            if (dayEl) {
                dayEl.classList.add('fc-selected');
            }
        });
    }
    
    /**
     * Entrer en mode sélection
     */
    function enterSelectionMode() {
        AvailabilityCalendar.selectionMode = true;
        AvailabilityCalendar.selectedDates = [];
        
        document.getElementById('selection-mode-panel').style.display = 'block';
        updateSelectionUI();
        
        // Changer le curseur sur le calendrier
        document.getElementById('calendar').style.cursor = 'crosshair';
    }
    
    /**
     * Sortir du mode sélection
     */
    function exitSelectionMode() {
        AvailabilityCalendar.selectionMode = false;
        AvailabilityCalendar.selectedDates = [];
        
        document.getElementById('selection-mode-panel').style.display = 'none';
        document.getElementById('calendar').style.cursor = 'default';
        
        highlightSelectedDates(); // Effacer les surlignages
    }
    
    /**
     * Ouvrir le modal de disponibilité
     */
    function openAvailabilityModal(event = null, dateSelection = null) {
        const modal = $('#availability-modal');
        const form = document.getElementById('availability-form');
        
        // Réinitialiser le formulaire
        form.reset();
        
        if (event) {
            // Mode édition
            const props = event.extendedProps;
            
            document.getElementById('availability-modal-title').textContent = 'Modifier la disponibilité';
            document.getElementById('availability-id').value = props.availability_id;
            document.getElementById('modal-booker').value = props.booker_id;
            document.getElementById('modal-date-from').value = props.date_from;
            document.getElementById('modal-date-to').value = props.date_to;
            
            document.getElementById('delete-availability-btn').style.display = 'inline-block';
            
        } else {
            // Mode création
            document.getElementById('availability-modal-title').textContent = 'Nouvelle disponibilité';
            document.getElementById('delete-availability-btn').style.display = 'none';
            
            if (dateSelection) {
                const startDate = dateSelection.startStr.split('T')[0];
                const endDate = new Date(dateSelection.end.getTime() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                
                document.getElementById('modal-date-from').value = startDate;
                document.getElementById('modal-date-to').value = endDate;
            } else {
                // Valeurs par défaut
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('modal-date-from').value = today;
                document.getElementById('modal-date-to').value = today;
            }
        }
        
        currentModal = modal;
        modal.modal('show');
    }
    
    /**
     * Ouvrir le modal de création en lot
     */
    function openBulkCreateModal() {
        const modal = $('#bulk-create-modal');
        const form = document.getElementById('bulk-create-form');
        
        form.reset();
        
        // Date de début par défaut : lundi prochain
        const nextMonday = getNextMonday();
        document.getElementById('bulk-start-date').value = nextMonday.toISOString().split('T')[0];
        
        modal.modal('show');
    }
    
    /**
     * Ouvrir le modal de copie de semaine
     */
    function openCopyWeekModal() {
        const modal = $('#copy-week-modal');
        const form = document.getElementById('copy-week-form');
        
        form.reset();
        
        modal.modal('show');
    }
    
    /**
     * Sauvegarder une disponibilité
     */
    function saveAvailability() {
        const form = document.getElementById('availability-form');
        
        if (!validateAvailabilityForm()) {
            return;
        }
        
        const formData = new FormData(form);
        const availabilityId = formData.get('availability_id');
        
        const url = availabilityId ? 
            AvailabilityCalendar.ajaxUrls.update_availability : 
            AvailabilityCalendar.ajaxUrls.create_availability;
        
        showLoading(true);
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showNotification(
                    availabilityId ? AvailabilityCalendar.messages.success_update : AvailabilityCalendar.messages.success_create,
                    'success'
                );
                
                $('#availability-modal').modal('hide');
                refreshCalendar();
            } else {
                showNotification(data.message || AvailabilityCalendar.messages.error, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Erreur lors de la sauvegarde:', error);
            showNotification(AvailabilityCalendar.messages.error, 'error');
        });
    }
    
    /**
     * Supprimer une disponibilité
     */
    function deleteAvailability() {
        const availabilityId = document.getElementById('availability-id').value;
        
        if (!availabilityId) return;
        
        showConfirmation(
            AvailabilityCalendar.messages.confirm_delete,
            () => executeDeleteAvailability(availabilityId)
        );
    }
    
    /**
     * Exécuter la suppression d'une disponibilité
     */
    function executeDeleteAvailability(availabilityId) {
        const formData = new FormData();
        formData.append('availability_id', availabilityId);
        
        showLoading(true);
        
        fetch(AvailabilityCalendar.ajaxUrls.delete_availability, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showNotification(AvailabilityCalendar.messages.success_delete, 'success');
                $('#availability-modal').modal('hide');
                refreshCalendar();
            } else {
                showNotification(data.message || AvailabilityCalendar.messages.error, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Erreur lors de la suppression:', error);
            showNotification(AvailabilityCalendar.messages.error, 'error');
        });
    }
    
    /**
     * Exécuter la création en lot
     */
    function executeBulkCreate() {
        const form = document.getElementById('bulk-create-form');
        
        if (!validateBulkCreateForm()) {
            return;
        }
        
        const formData = new FormData(form);
        
        showLoading(true);
        
        fetch(AvailabilityCalendar.ajaxUrls.bulk_create, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showNotification(
                    AvailabilityCalendar.messages.success_bulk_create + ': ' + data.message,
                    'success'
                );
                
                $('#bulk-create-modal').modal('hide');
                refreshCalendar();
            } else {
                showNotification(data.message || AvailabilityCalendar.messages.error, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Erreur lors de la création en lot:', error);
            showNotification(AvailabilityCalendar.messages.error, 'error');
        });
    }
    
    /**
     * Exécuter la copie de semaine
     */
    function executeCopyWeek() {
        const form = document.getElementById('copy-week-form');
        
        if (!validateCopyWeekForm()) {
            return;
        }
        
        const formData = new FormData(form);
        
        // Convertir les semaines en dates de lundi
        const sourceWeek = document.getElementById('copy-source-week').value;
        const targetWeek = document.getElementById('copy-target-week').value;
        
        formData.set('source_week', weekToMonday(sourceWeek));
        formData.set('target_week', weekToMonday(targetWeek));
        
        showLoading(true);
        
        fetch(AvailabilityCalendar.ajaxUrls.copy_week, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            
            if (data.success) {
                showNotification(
                    AvailabilityCalendar.messages.success_copy + ': ' + data.message,
                    'success'
                );
                
                $('#copy-week-modal').modal('hide');
                refreshCalendar();
            } else {
                showNotification(data.message || AvailabilityCalendar.messages.error, 'error');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Erreur lors de la copie:', error);
            showNotification(AvailabilityCalendar.messages.error, 'error');
        });
    }
    
    /**
     * Appliquer la sélection en lot
     */
    function applyBulkSelection() {
        const bookerFilter = document.getElementById('booker-filter').value;
        
        if (!bookerFilter || bookerFilter === 'all') {
            showNotification(AvailabilityCalendar.messages.no_booker_selected, 'error');
            return;
        }
        
        if (AvailabilityCalendar.selectedDates.length === 0) {
            showNotification(AvailabilityCalendar.messages.no_days_selected, 'error');
            return;
        }
        
        showConfirmation(
            AvailabilityCalendar.messages.confirm_bulk_create,
            () => {
                // Créer une disponibilité pour chaque date sélectionnée
                let created = 0;
                let errors = 0;
                const total = AvailabilityCalendar.selectedDates.length;
                
                AvailabilityCalendar.selectedDates.forEach((date, index) => {
                    const formData = new FormData();
                    formData.append('booker_id', bookerFilter);
                    formData.append('date_from', date);
                    formData.append('date_to', date);
                    
                    fetch(AvailabilityCalendar.ajaxUrls.create_availability, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            created++;
                        } else {
                            errors++;
                        }
                        
                        // Si c'est la dernière requête
                        if (created + errors === total) {
                            showNotification(
                                `${created} disponibilité(s) créée(s), ${errors} erreur(s)`,
                                created > 0 ? 'success' : 'error'
                            );
                            
                            exitSelectionMode();
                            refreshCalendar();
                        }
                    })
                    .catch(() => {
                        errors++;
                        
                        if (created + errors === total) {
                            showNotification(
                                `${created} disponibilité(s) créée(s), ${errors} erreur(s)`,
                                'error'
                            );
                            
                            exitSelectionMode();
                            refreshCalendar();
                        }
                    });
                });
            }
        );
    }
    
    /**
     * Mettre à jour les dates d'une disponibilité
     */
    function updateAvailabilityDates(availabilityId, newDateFrom, newDateTo) {
        const formData = new FormData();
        formData.append('availability_id', availabilityId);
        formData.append('new_date_from', newDateFrom);
        formData.append('new_date_to', newDateTo);
        
        fetch(AvailabilityCalendar.ajaxUrls.update_availability, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(AvailabilityCalendar.messages.success_update, 'success');
            } else {
                showNotification(data.message || AvailabilityCalendar.messages.error, 'error');
                refreshCalendar(); // Revert on error
            }
        })
        .catch(error => {
            console.error('Erreur lors de la mise à jour:', error);
            showNotification(AvailabilityCalendar.messages.error, 'error');
            refreshCalendar();
        });
    }
    
    /**
     * Sélectionner des jours spécifiques
     */
    function selectDays(days) {
        const checkboxes = document.querySelectorAll('input[name="selected_days[]"]');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = days.includes(parseInt(checkbox.value));
        });
    }
    
    /**
     * Appliquer un créneau prédéfini
     */
    function applyPresetTime(presetKey) {
        // Cette fonction pourrait être étendue pour gérer des créneaux horaires prédéfinis
        // Pour l'instant, elle ne fait rien car les disponibilités sont par jour complet
    }
    
    /**
     * Valider le formulaire de disponibilité
     */
    function validateAvailabilityForm() {
        const booker = document.getElementById('modal-booker').value;
        const dateFrom = document.getElementById('modal-date-from').value;
        const dateTo = document.getElementById('modal-date-to').value;
        
        if (!booker || !dateFrom || !dateTo) {
            showNotification(AvailabilityCalendar.messages.validation_required, 'error');
            return false;
        }
        
        return validateDateRange();
    }
    
    /**
     * Valider le formulaire de création en lot
     */
    function validateBulkCreateForm() {
        const booker = document.getElementById('bulk-booker').value;
        const startDate = document.getElementById('bulk-start-date').value;
        const selectedDays = document.querySelectorAll('input[name="selected_days[]"]:checked');
        
        if (!booker) {
            showNotification(AvailabilityCalendar.messages.no_booker_selected, 'error');
            return false;
        }
        
        if (!startDate) {
            showNotification('Veuillez sélectionner une date de début', 'error');
            return false;
        }
        
        if (selectedDays.length === 0) {
            showNotification(AvailabilityCalendar.messages.no_days_selected, 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Valider le formulaire de copie de semaine
     */
    function validateCopyWeekForm() {
        const booker = document.getElementById('copy-booker').value;
        const sourceWeek = document.getElementById('copy-source-week').value;
        const targetWeek = document.getElementById('copy-target-week').value;
        
        if (!booker || !sourceWeek || !targetWeek) {
            showNotification('Veuillez remplir tous les champs', 'error');
            return false;
        }
        
        if (sourceWeek === targetWeek) {
            showNotification('Les semaines source et destination doivent être différentes', 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Valider la plage de dates
     */
    function validateDateRange() {
        const dateFrom = new Date(document.getElementById('modal-date-from').value);
        const dateTo = new Date(document.getElementById('modal-date-to').value);
        
        if (dateFrom > dateTo) {
            showNotification(AvailabilityCalendar.messages.invalid_date_range, 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtenir le lundi suivant
     */
    function getNextMonday() {
        const today = new Date();
        const dayOfWeek = today.getDay();
        const daysUntilMonday = dayOfWeek === 0 ? 1 : 8 - dayOfWeek;
        
        const nextMonday = new Date(today);
        nextMonday.setDate(today.getDate() + daysUntilMonday);
        
        return nextMonday;
    }
    
    /**
     * Convertir une semaine (YYYY-Www) en date de lundi
     */
    function weekToMonday(weekStr) {
        const [year, week] = weekStr.split('-W');
        const jan1 = new Date(year, 0, 1);
        const dayOfWeek = jan1.getDay();
        const daysToFirstMonday = dayOfWeek === 0 ? 1 : 8 - dayOfWeek;
        
        const firstMonday = new Date(jan1);
        firstMonday.setDate(jan1.getDate() + daysToFirstMonday);
        
        const targetMonday = new Date(firstMonday);
        targetMonday.setDate(firstMonday.getDate() + (week - 1) * 7);
        
        return targetMonday.toISOString().split('T')[0];
    }
    
    /**
     * Rafraîchir le calendrier
     */
    function refreshCalendar() {
        if (calendar) {
            calendar.refetchEvents();
        }
    }
    
    /**
     * Afficher une notification
     */
    function showNotification(message, type = 'info') {
        if (typeof $.growl === 'function') {
            $.growl({ message: message }, { type: type });
        } else {
            alert(message);
        }
    }
    
    /**
     * Afficher une confirmation
     */
    function showConfirmation(message, callback) {
        document.getElementById('confirm-message').textContent = message;
        pendingAction = callback;
        $('#confirm-modal').modal('show');
    }
    
    /**
     * Afficher/masquer le loading
     */
    function showLoading(show) {
        if (show) {
            document.body.style.cursor = 'wait';
        } else {
            document.body.style.cursor = 'default';
        }
    }
    
    // Initialiser les tooltips Bootstrap
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
    });
    
    // Exposer des fonctions pour usage externe
    window.AvailabilityCalendarManager = {
        enterSelectionMode: enterSelectionMode,
        exitSelectionMode: exitSelectionMode,
        refreshCalendar: refreshCalendar
    };
});
