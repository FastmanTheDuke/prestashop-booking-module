/**
 * JavaScript pour le calendrier de gestion des disponibilités
 * Utilise FullCalendar pour une interface interactive moderne
 */

var AvailabilityCalendar = {
    calendar: null,
    selectedEvents: [],
    currentBookerFilter: '',
    
    /**
     * Initialisation du calendrier
     */
    init: function() {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'fr',
            firstDay: bookingCalendarConfig.firstDay || 1,
            slotMinTime: bookingCalendarConfig.minTime || '08:00',
            slotMaxTime: bookingCalendarConfig.maxTime || '20:00',
            slotDuration: bookingCalendarConfig.slotDuration || '00:30:00',
            height: 'auto',
            selectable: true,
            selectMirror: true,
            editable: true,
            eventResizableFromStart: true,
            businessHours: bookingCalendarConfig.businessHours,
            
            // Sources d'événements
            events: {
                url: ajaxUrl,
                method: 'POST',
                extraParams: {
                    ajax: 1,
                    action: 'getAvailabilities',
                    token: currentToken
                },
                failure: function() {
                    AvailabilityCalendar.showMessage('Erreur lors du chargement des données', 'error');
                }
            },

            // Callbacks
            select: this.handleSelect.bind(this),
            eventClick: this.handleEventClick.bind(this),
            eventDrop: this.handleEventDrop.bind(this),
            eventResize: this.handleEventResize.bind(this),
            eventDidMount: this.handleEventDidMount.bind(this)
        });

        this.calendar.render();
        this.initEventHandlers();
    },

    /**
     * Initialisation des gestionnaires d'événements
     */
    initEventHandlers: function() {
        // Filtrage par booker
        $('#booker-filter').on('change', function() {
            AvailabilityCalendar.currentBookerFilter = $(this).val();
            AvailabilityCalendar.refreshCalendar();
        });

        // Bouton ajouter disponibilité
        $('#add-availability').on('click', function() {
            AvailabilityCalendar.showCreateModal();
        });

        // Actions groupées
        $('#bulk-actions').on('click', function() {
            if (AvailabilityCalendar.selectedEvents.length > 0) {
                AvailabilityCalendar.showBulkActionsModal();
            }
        });

        // Sélection multiple avec Ctrl+Click
        $(document).on('keydown keyup', function(e) {
            if (e.ctrlKey || e.metaKey) {
                $('body').addClass('multi-select-mode');
            } else {
                $('body').removeClass('multi-select-mode');
            }
        });
    },

    /**
     * Gestionnaire de sélection de période
     */
    handleSelect: function(selectInfo) {
        this.showCreateModal({
            start: selectInfo.start,
            end: selectInfo.end
        });
        this.calendar.unselect();
    },

    /**
     * Gestionnaire de clic sur événement
     */
    handleEventClick: function(clickInfo) {
        if ($('body').hasClass('multi-select-mode')) {
            // Mode sélection multiple
            this.toggleEventSelection(clickInfo.event);
        } else {
            // Mode édition simple
            this.showEditModal(clickInfo.event);
        }
    },

    /**
     * Gestionnaire de déplacement d'événement
     */
    handleEventDrop: function(dropInfo) {
        var event = dropInfo.event;
        var data = {
            ajax: 1,
            action: 'updateAvailability',
            token: currentToken,
            id_auth: event.extendedProps.id_auth,
            date_from: this.formatDate(event.start),
            date_to: this.formatDate(event.end || event.start),
            time_from: this.formatTime(event.start),
            time_to: this.formatTime(event.end || event.start)
        };

        this.sendAjaxRequest(data, function(response) {
            if (!response.success) {
                AvailabilityCalendar.showMessage(response.message, 'error');
                dropInfo.revert();
            } else {
                AvailabilityCalendar.showMessage('Disponibilité mise à jour', 'success');
            }
        });
    },

    /**
     * Gestionnaire de redimensionnement d'événement
     */
    handleEventResize: function(resizeInfo) {
        this.handleEventDrop(resizeInfo); // Même logique que le déplacement
    },

    /**
     * Rendu personnalisé des événements
     */
    handleEventDidMount: function(info) {
        var event = info.event;
        var props = event.extendedProps;
        
        // Ajouter des classes CSS selon le type
        if (props.recurring) {
            info.el.classList.add('recurring-event');
        }
        
        // Ajouter tooltip avec détails
        $(info.el).tooltip({
            title: this.getEventTooltip(event),
            html: true,
            placement: 'top'
        });

        // Ajouter indicateur de capacité
        var capacityIndicator = document.createElement('div');
        capacityIndicator.className = 'capacity-indicator';
        capacityIndicator.innerHTML = props.current_bookings + '/' + props.max_bookings;
        info.el.appendChild(capacityIndicator);
    },

    /**
     * Toggle sélection d'événement
     */
    toggleEventSelection: function(event) {
        var eventId = event.id;
        var index = this.selectedEvents.indexOf(eventId);
        
        if (index > -1) {
            // Désélectionner
            this.selectedEvents.splice(index, 1);
            event.setProp('backgroundColor', event.backgroundColor);
        } else {
            // Sélectionner
            this.selectedEvents.push(eventId);
            event.setProp('backgroundColor', '#007bff');
        }
        
        // Mettre à jour le bouton d'actions groupées
        $('#bulk-actions').prop('disabled', this.selectedEvents.length === 0);
        $('#bulk-actions').text('Actions groupées (' + this.selectedEvents.length + ')');
    },

    /**
     * Afficher modal de création
     */
    showCreateModal: function(selectInfo) {
        var modal = this.createModal('Créer une disponibilité', this.getCreateForm(selectInfo));
        
        modal.find('.btn-primary').on('click', function() {
            AvailabilityCalendar.submitCreateForm(modal);
        });
        
        modal.modal('show');
    },

    /**
     * Afficher modal d'édition
     */
    showEditModal: function(event) {
        var modal = this.createModal('Modifier la disponibilité', this.getEditForm(event));
        
        modal.find('.btn-primary').on('click', function() {
            AvailabilityCalendar.submitEditForm(modal, event);
        });
        
        modal.find('.btn-danger').on('click', function() {
            AvailabilityCalendar.deleteAvailability(event.extendedProps.id_auth);
            modal.modal('hide');
        });
        
        modal.modal('show');
    },

    /**
     * Afficher modal d'actions groupées
     */
    showBulkActionsModal: function() {
        var content = `
            <div class="form-group">
                <label>Action à effectuer sur ${this.selectedEvents.length} élément(s) sélectionné(s) :</label>
                <select class="form-control" id="bulk-action-select">
                    <option value="activate">Activer</option>
                    <option value="deactivate">Désactiver</option>
                    <option value="delete">Supprimer</option>
                </select>
            </div>
        `;
        
        var modal = this.createModal('Actions groupées', content);
        
        modal.find('.btn-primary').on('click', function() {
            var action = modal.find('#bulk-action-select').val();
            AvailabilityCalendar.executeBulkAction(action);
            modal.modal('hide');
        });
        
        modal.modal('show');
    },

    /**
     * Générer le formulaire de création
     */
    getCreateForm: function(selectInfo) {
        var startDate = selectInfo ? this.formatDate(selectInfo.start) : '';
        var endDate = selectInfo ? this.formatDate(selectInfo.end || selectInfo.start) : '';
        
        return `
            <form id="availability-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Élément à réserver *</label>
                            <select class="form-control" name="id_booker" required>
                                <option value="">Sélectionner...</option>
                                ${this.getBookerOptions()}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nombre de réservations max *</label>
                            <input type="number" class="form-control" name="max_bookings" value="1" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" class="form-control" name="date_from" value="${startDate}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date de fin *</label>
                            <input type="date" class="form-control" name="date_to" value="${endDate}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Heure de début *</label>
                            <input type="time" class="form-control" name="time_from" value="08:00" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Heure de fin *</label>
                            <input type="time" class="form-control" name="time_to" value="18:00" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prix spécifique</label>
                            <input type="number" class="form-control" name="price_override" step="0.01" placeholder="Laisser vide = prix du booker">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="recurring" value="1"> Récurrent
                            </label>
                        </div>
                    </div>
                </div>
                <div class="recurring-options" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Type de récurrence</label>
                                <select class="form-control" name="recurring_type">
                                    <option value="daily">Quotidien</option>
                                    <option value="weekly">Hebdomadaire</option>
                                    <option value="monthly">Mensuel</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fin de récurrence</label>
                                <input type="date" class="form-control" name="recurring_end">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes internes</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
            </form>
        `;
    },

    /**
     * Générer le formulaire d'édition
     */
    getEditForm: function(event) {
        var props = event.extendedProps;
        
        return `
            <form id="availability-edit-form">
                <input type="hidden" name="id_auth" value="${props.id_auth}">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Élément</label>
                            <input type="text" class="form-control" value="${props.booker_name}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nombre de réservations max *</label>
                            <input type="number" class="form-control" name="max_bookings" value="${props.max_bookings}" min="${props.current_bookings}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" class="form-control" name="date_from" value="${this.formatDate(event.start)}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Date de fin *</label>
                            <input type="date" class="form-control" name="date_to" value="${this.formatDate(event.end || event.start)}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Heure de début *</label>
                            <input type="time" class="form-control" name="time_from" value="${this.formatTime(event.start)}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Heure de fin *</label>
                            <input type="time" class="form-control" name="time_to" value="${this.formatTime(event.end || event.start)}" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Prix spécifique</label>
                    <input type="number" class="form-control" name="price_override" value="${props.price || ''}" step="0.01">
                </div>
                <div class="form-group">
                    <label>Notes internes</label>
                    <textarea class="form-control" name="notes" rows="3">${props.notes || ''}</textarea>
                </div>
            </form>
        `;
    },

    /**
     * Soumettre le formulaire de création
     */
    submitCreateForm: function(modal) {
        var formData = this.serializeForm(modal.find('#availability-form'));
        formData.ajax = 1;
        formData.action = 'createAvailability';
        formData.token = currentToken;

        this.sendAjaxRequest(formData, function(response) {
            if (response.success) {
                AvailabilityCalendar.showMessage('Disponibilité créée avec succès', 'success');
                AvailabilityCalendar.refreshCalendar();
                modal.modal('hide');
            } else {
                AvailabilityCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Soumettre le formulaire d'édition
     */
    submitEditForm: function(modal, event) {
        var formData = this.serializeForm(modal.find('#availability-edit-form'));
        formData.ajax = 1;
        formData.action = 'updateAvailability';
        formData.token = currentToken;

        this.sendAjaxRequest(formData, function(response) {
            if (response.success) {
                AvailabilityCalendar.showMessage('Disponibilité mise à jour avec succès', 'success');
                AvailabilityCalendar.refreshCalendar();
                modal.modal('hide');
            } else {
                AvailabilityCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Supprimer une disponibilité
     */
    deleteAvailability: function(id_auth) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette disponibilité ?')) {
            return;
        }

        var data = {
            ajax: 1,
            action: 'deleteAvailability',
            token: currentToken,
            id_auth: id_auth
        };

        this.sendAjaxRequest(data, function(response) {
            if (response.success) {
                AvailabilityCalendar.showMessage('Disponibilité supprimée', 'success');
                AvailabilityCalendar.refreshCalendar();
            } else {
                AvailabilityCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Exécuter une action groupée
     */
    executeBulkAction: function(action) {
        var data = {
            ajax: 1,
            action: 'bulkAction',
            token: currentToken,
            action: action,
            ids: this.selectedEvents
        };

        this.sendAjaxRequest(data, function(response) {
            AvailabilityCalendar.showMessage(response.message, response.success ? 'success' : 'error');
            AvailabilityCalendar.selectedEvents = [];
            AvailabilityCalendar.refreshCalendar();
            $('#bulk-actions').prop('disabled', true).text('Actions groupées');
        });
    },

    /**
     * Utilitaires
     */
    refreshCalendar: function() {
        if (this.calendar) {
            var source = this.calendar.getEventSources()[0];
            if (source) {
                source.refetch();
            }
        }
    },

    createModal: function(title, content) {
        var modalId = 'availability-modal-' + Date.now();
        var modal = $(`
            <div class="modal fade" id="${modalId}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">${title}</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body">${content}</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-danger" style="display: none;">Supprimer</button>
                            <button type="button" class="btn btn-primary">Sauvegarder</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        modal.on('hidden.bs.modal', function() {
            modal.remove();
        });
        
        // Gestion de la récurrence
        modal.find('input[name="recurring"]').on('change', function() {
            modal.find('.recurring-options').toggle(this.checked);
        });
        
        return modal;
    },

    serializeForm: function(form) {
        var data = {};
        form.find('input, select, textarea').each(function() {
            var $this = $(this);
            var name = $this.attr('name');
            var value = $this.val();
            
            if ($this.attr('type') === 'checkbox') {
                value = $this.is(':checked') ? 1 : 0;
            }
            
            if (name) {
                data[name] = value;
            }
        });
        return data;
    },

    sendAjaxRequest: function(data, callback) {
        $.post(ajaxUrl, data)
            .done(function(response) {
                try {
                    var result = typeof response === 'string' ? JSON.parse(response) : response;
                    callback(result);
                } catch (e) {
                    callback({ success: false, message: 'Erreur de communication' });
                }
            })
            .fail(function() {
                callback({ success: false, message: 'Erreur de communication' });
            });
    },

    showMessage: function(message, type) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var alert = $(`<div class="alert ${alertClass} alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            ${message}
        </div>`);
        
        $('.booking-calendar-container').prepend(alert);
        
        setTimeout(function() {
            alert.fadeOut();
        }, 5000);
    },

    formatDate: function(date) {
        return moment(date).format('YYYY-MM-DD');
    },

    formatTime: function(date) {
        return moment(date).format('HH:mm');
    },

    getEventTooltip: function(event) {
        var props = event.extendedProps;
        return `
            <strong>${props.booker_name}</strong><br>
            Réservations: ${props.current_bookings}/${props.max_bookings}<br>
            Prix: ${props.price}€<br>
            ${props.notes ? 'Notes: ' + props.notes : ''}
        `;
    },

    getBookerOptions: function() {
        // Cette fonction devrait être alimentée par les données du serveur
        // Pour l'instant, on retourne une chaîne vide
        return '';
    }
};

// Initialisation au chargement de la page
$(document).ready(function() {
    if (typeof bookingCalendarConfig !== 'undefined' && typeof ajaxUrl !== 'undefined') {
        AvailabilityCalendar.init();
    }
});