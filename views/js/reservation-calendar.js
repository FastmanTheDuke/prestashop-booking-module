/**
 * JavaScript pour le calendrier de gestion des réservations
 * Interface interactive pour visualiser et gérer les réservations clients
 */

var ReservationCalendar = {
    calendar: null,
    selectedEvents: [],
    currentBookerFilter: '',
    currentStatusFilter: '',
    
    /**
     * Initialisation du calendrier des réservations
     */
    init: function() {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
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
                extraParams: function() {
                    return {
                        ajax: 1,
                        action: 'getReservations',
                        token: currentToken,
                        id_booker: ReservationCalendar.currentBookerFilter,
                        status_filter: ReservationCalendar.currentStatusFilter
                    };
                },
                failure: function() {
                    ReservationCalendar.showMessage('Erreur lors du chargement des réservations', 'error');
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
        this.loadStatistics();
    },

    /**
     * Initialisation des gestionnaires d'événements
     */
    initEventHandlers: function() {
        // Filtrage par booker
        $('#booker-filter').on('change', function() {
            ReservationCalendar.currentBookerFilter = $(this).val();
            ReservationCalendar.refreshCalendar();
        });

        // Filtrage par statut
        $('#status-filter').on('change', function() {
            ReservationCalendar.currentStatusFilter = $(this).val();
            ReservationCalendar.refreshCalendar();
        });

        // Bouton ajouter réservation
        $('#add-reservation').on('click', function() {
            ReservationCalendar.showCreateModal();
        });

        // Actions en lot - Accepter
        $('#bulk-accept').on('click', function() {
            if (ReservationCalendar.selectedEvents.length > 0) {
                ReservationCalendar.executeBulkAction('accept');
            }
        });

        // Actions en lot - Créer commandes
        $('#bulk-create-orders').on('click', function() {
            if (ReservationCalendar.selectedEvents.length > 0) {
                ReservationCalendar.executeBulkAction('create_orders');
            }
        });

        // Actions en lot - Annuler
        $('#bulk-cancel').on('click', function() {
            if (ReservationCalendar.selectedEvents.length > 0) {
                ReservationCalendar.executeBulkAction('cancel');
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
            // Mode consultation/édition
            this.showReservationDetails(clickInfo.event);
        }
    },

    /**
     * Gestionnaire de déplacement de réservation
     */
    handleEventDrop: function(dropInfo) {
        var event = dropInfo.event;
        var data = {
            ajax: 1,
            action: 'updateReservation',
            token: currentToken,
            id_reserved: event.extendedProps.id_reserved,
            date_reserved: this.formatDate(event.start),
            date_to: this.formatDate(event.end || event.start),
            hour_from: event.start.getHours(),
            hour_to: (event.end || event.start).getHours()
        };

        this.sendAjaxRequest(data, function(response) {
            if (!response.success) {
                ReservationCalendar.showMessage(response.message, 'error');
                dropInfo.revert();
            } else {
                ReservationCalendar.showMessage('Réservation déplacée avec succès', 'success');
            }
        });
    },

    /**
     * Gestionnaire de redimensionnement de réservation
     */
    handleEventResize: function(resizeInfo) {
        this.handleEventDrop(resizeInfo);
    },

    /**
     * Rendu personnalisé des événements
     */
    handleEventDidMount: function(info) {
        var event = info.event;
        var props = event.extendedProps;
        
        // Ajouter des classes CSS selon le statut
        info.el.classList.add('status-' + props.status);
        
        // Ajouter tooltip avec détails
        $(info.el).tooltip({
            title: this.getReservationTooltip(event),
            html: true,
            placement: 'top'
        });

        // Ajouter indicateur de prix si présent
        if (props.total_price > 0) {
            var priceIndicator = document.createElement('div');
            priceIndicator.className = 'price-indicator';
            priceIndicator.innerHTML = props.total_price + '€';
            info.el.appendChild(priceIndicator);
        }

        // Ajouter indicateur de paiement
        if (props.deposit_paid > 0) {
            var depositIndicator = document.createElement('div');
            depositIndicator.className = 'deposit-indicator';
            depositIndicator.innerHTML = '💳';
            info.el.appendChild(depositIndicator);
        }
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
        
        // Mettre à jour les boutons d'actions groupées
        var count = this.selectedEvents.length;
        var disabled = count === 0;
        
        $('#bulk-accept, #bulk-create-orders, #bulk-cancel').prop('disabled', disabled);
        
        if (count > 0) {
            $('#bulk-accept').text('Accepter (' + count + ')');
            $('#bulk-create-orders').text('Créer commandes (' + count + ')');
            $('#bulk-cancel').text('Annuler (' + count + ')');
        } else {
            $('#bulk-accept').text('Accepter');
            $('#bulk-create-orders').text('Créer commandes');
            $('#bulk-cancel').text('Annuler');
        }
    },

    /**
     * Afficher modal de création de réservation
     */
    showCreateModal: function(selectInfo) {
        var modal = this.createModal('Créer une réservation', this.getCreateForm(selectInfo));
        
        modal.find('.btn-primary').on('click', function() {
            ReservationCalendar.submitCreateForm(modal);
        });
        
        modal.modal('show');
    },

    /**
     * Afficher détails d'une réservation
     */
    showReservationDetails: function(event) {
        var data = {
            ajax: 1,
            action: 'getReservationDetails',
            token: currentToken,
            id_reserved: event.extendedProps.id_reserved
        };

        this.sendAjaxRequest(data, function(response) {
            if (response.success) {
                var modal = ReservationCalendar.createModal(
                    'Détails de la réservation #' + response.data.booking_reference, 
                    ReservationCalendar.getDetailsView(response.data)
                );
                
                modal.find('.btn-primary').text('Modifier').on('click', function() {
                    modal.modal('hide');
                    ReservationCalendar.showEditModal(event, response.data);
                });
                
                modal.find('.btn-danger').show().text('Supprimer').on('click', function() {
                    ReservationCalendar.deleteReservation(event.extendedProps.id_reserved);
                    modal.modal('hide');
                });
                
                modal.modal('show');
            } else {
                ReservationCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Afficher modal d'édition de réservation
     */
    showEditModal: function(event, data) {
        var modal = this.createModal('Modifier la réservation', this.getEditForm(data || event.extendedProps));
        
        modal.find('.btn-primary').on('click', function() {
            ReservationCalendar.submitEditForm(modal, event);
        });
        
        modal.modal('show');
    },

    /**
     * Générer le formulaire de création
     */
    getCreateForm: function(selectInfo) {
        var startDate = selectInfo ? this.formatDate(selectInfo.start) : '';
        var startHour = selectInfo ? selectInfo.start.getHours() : 9;
        var endHour = selectInfo ? (selectInfo.end || selectInfo.start).getHours() : 17;
        
        return `
            <form id="reservation-form">
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
                            <label>Statut</label>
                            <select class="form-control" name="status">
                                ${this.getStatusOptions()}
                            </select>
                        </div>
                    </div>
                </div>
                
                <h5>Informations client</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" class="form-control" name="customer_firstname" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" class="form-control" name="customer_lastname" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" class="form-control" name="customer_email" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="customer_phone">
                        </div>
                    </div>
                </div>
                
                <h5>Détails de la réservation</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" class="form-control" name="date_reserved" value="${startDate}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de fin</label>
                            <input type="date" class="form-control" name="date_to" value="${startDate}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Heure début *</label>
                            <select class="form-control" name="hour_from" required>
                                ${this.getHourOptions(startHour)}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Heure fin *</label>
                            <select class="form-control" name="hour_to" required>
                                ${this.getHourOptions(endHour)}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Prix total</label>
                            <input type="number" class="form-control" name="total_price" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Notes client</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Notes admin</label>
                            <textarea class="form-control" name="admin_notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </form>
        `;
    },

    /**
     * Générer le formulaire d'édition
     */
    getEditForm: function(data) {
        return `
            <form id="reservation-edit-form">
                <input type="hidden" name="id_reserved" value="${data.id_reserved}">
                
                <div class="alert alert-info">
                    <strong>Référence :</strong> ${data.booking_reference}
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Élément réservé</label>
                            <input type="text" class="form-control" value="${data.booker_name}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Statut</label>
                            <select class="form-control" name="status">
                                ${this.getStatusOptions(data.status)}
                            </select>
                        </div>
                    </div>
                </div>
                
                <h5>Informations client</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" class="form-control" name="customer_firstname" value="${data.customer_firstname}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" class="form-control" name="customer_lastname" value="${data.customer_lastname}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" class="form-control" name="customer_email" value="${data.customer_email}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="customer_phone" value="${data.customer_phone || ''}">
                        </div>
                    </div>
                </div>
                
                <h5>Détails de la réservation</h5>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de début *</label>
                            <input type="date" class="form-control" name="date_reserved" value="${data.date_reserved}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de fin</label>
                            <input type="date" class="form-control" name="date_to" value="${data.date_to || data.date_reserved}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Heure début *</label>
                            <select class="form-control" name="hour_from" required>
                                ${this.getHourOptions(data.hour_from)}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Heure fin *</label>
                            <select class="form-control" name="hour_to" required>
                                ${this.getHourOptions(data.hour_to)}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Prix total</label>
                            <input type="number" class="form-control" name="total_price" value="${data.total_price}" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Notes client</label>
                            <textarea class="form-control" name="notes" rows="3">${data.notes || ''}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Notes admin</label>
                            <textarea class="form-control" name="admin_notes" rows="3">${data.admin_notes || ''}</textarea>
                        </div>
                    </div>
                </div>
            </form>
        `;
    },

    /**
     * Générer la vue détaillée d'une réservation
     */
    getDetailsView: function(data) {
        var statusLabels = bookingStatuses || {};
        var statusInfo = statusLabels[data.status] || { label: 'Inconnu', color: '#6c757d' };
        
        return `
            <div class="reservation-details">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Informations générales</h5>
                        <table class="table table-borderless">
                            <tr><td><strong>Référence :</strong></td><td>${data.booking_reference}</td></tr>
                            <tr><td><strong>Élément :</strong></td><td>${data.booker_name}</td></tr>
                            <tr><td><strong>Lieu :</strong></td><td>${data.booker_location || 'Non spécifié'}</td></tr>
                            <tr><td><strong>Statut :</strong></td><td><span class="label" style="background-color: ${statusInfo.color}">${statusInfo.label}</span></td></tr>
                            <tr><td><strong>Paiement :</strong></td><td>${this.getPaymentStatusLabel(data.payment_status)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Informations client</h5>
                        <table class="table table-borderless">
                            <tr><td><strong>Nom :</strong></td><td>${data.customer_firstname} ${data.customer_lastname}</td></tr>
                            <tr><td><strong>Email :</strong></td><td><a href="mailto:${data.customer_email}">${data.customer_email}</a></td></tr>
                            <tr><td><strong>Téléphone :</strong></td><td>${data.customer_phone || 'Non renseigné'}</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>Détails de la réservation</h5>
                        <table class="table table-borderless">
                            <tr><td><strong>Date début :</strong></td><td>${this.formatDateFr(data.date_reserved)}</td></tr>
                            <tr><td><strong>Date fin :</strong></td><td>${this.formatDateFr(data.date_to || data.date_reserved)}</td></tr>
                            <tr><td><strong>Horaires :</strong></td><td>${data.hour_from}h - ${data.hour_to}h</td></tr>
                            <tr><td><strong>Prix total :</strong></td><td>${data.total_price}€</td></tr>
                            <tr><td><strong>Caution versée :</strong></td><td>${data.deposit_paid}€</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Notes</h5>
                        <div class="form-group">
                            <label>Notes client :</label>
                            <div class="well well-sm">${data.notes || 'Aucune note'}</div>
                        </div>
                        <div class="form-group">
                            <label>Notes admin :</label>
                            <div class="well well-sm">${data.admin_notes || 'Aucune note'}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Soumettre le formulaire de création
     */
    submitCreateForm: function(modal) {
        var formData = this.serializeForm(modal.find('#reservation-form'));
        formData.ajax = 1;
        formData.action = 'createReservation';
        formData.token = currentToken;

        this.sendAjaxRequest(formData, function(response) {
            if (response.success) {
                ReservationCalendar.showMessage('Réservation créée avec succès', 'success');
                ReservationCalendar.refreshCalendar();
                ReservationCalendar.loadStatistics();
                modal.modal('hide');
            } else {
                ReservationCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Soumettre le formulaire d'édition
     */
    submitEditForm: function(modal, event) {
        var formData = this.serializeForm(modal.find('#reservation-edit-form'));
        formData.ajax = 1;
        formData.action = 'updateReservation';
        formData.token = currentToken;

        this.sendAjaxRequest(formData, function(response) {
            if (response.success) {
                ReservationCalendar.showMessage('Réservation mise à jour avec succès', 'success');
                ReservationCalendar.refreshCalendar();
                ReservationCalendar.loadStatistics();
                modal.modal('hide');
            } else {
                ReservationCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Supprimer une réservation
     */
    deleteReservation: function(id_reserved) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette réservation ?')) {
            return;
        }

        var data = {
            ajax: 1,
            action: 'deleteReservation',
            token: currentToken,
            id_reserved: id_reserved
        };

        this.sendAjaxRequest(data, function(response) {
            if (response.success) {
                ReservationCalendar.showMessage('Réservation supprimée', 'success');
                ReservationCalendar.refreshCalendar();
                ReservationCalendar.loadStatistics();
            } else {
                ReservationCalendar.showMessage(response.message, 'error');
            }
        });
    },

    /**
     * Exécuter une action groupée
     */
    executeBulkAction: function(action) {
        var actionLabels = {
            'accept': 'accepter',
            'cancel': 'annuler',
            'create_orders': 'créer les commandes pour'
        };
        
        var label = actionLabels[action] || action;
        if (!confirm('Êtes-vous sûr de vouloir ' + label + ' ' + this.selectedEvents.length + ' réservation(s) ?')) {
            return;
        }

        var data = {
            ajax: 1,
            action: 'bulkAction',
            token: currentToken,
            action: action,
            ids: this.selectedEvents
        };

        this.sendAjaxRequest(data, function(response) {
            ReservationCalendar.showMessage(response.message, response.success ? 'success' : 'error');
            ReservationCalendar.selectedEvents = [];
            ReservationCalendar.refreshCalendar();
            ReservationCalendar.loadStatistics();
            $('#bulk-accept, #bulk-create-orders, #bulk-cancel').prop('disabled', true);
            $('#bulk-accept').text('Accepter');
            $('#bulk-create-orders').text('Créer commandes');
            $('#bulk-cancel').text('Annuler');
        });
    },

    /**
     * Charger les statistiques
     */
    loadStatistics: function() {
        // Cette fonction pourrait charger des statistiques en temps réel
        // Pour l'instant, on se contente de mettre à jour l'affichage
    },

    /**
     * Utilitaires
     */
    refreshCalendar: function() {
        if (this.calendar) {
            this.calendar.refetchEvents();
        }
    },

    createModal: function(title, content) {
        var modalId = 'reservation-modal-' + Date.now();
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
                            <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
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

    formatDateFr: function(date) {
        return moment(date).format('DD/MM/YYYY');
    },

    formatTime: function(date) {
        return moment(date).format('HH:mm');
    },

    getReservationTooltip: function(event) {
        var props = event.extendedProps;
        return `
            <strong>${props.customer_name}</strong><br>
            <strong>Élément:</strong> ${props.booker_name}<br>
            <strong>Prix:</strong> ${props.total_price}€<br>
            <strong>Statut:</strong> ${props.status_label}<br>
            <strong>Email:</strong> ${props.customer_email}<br>
            ${props.customer_phone ? '<strong>Tél:</strong> ' + props.customer_phone + '<br>' : ''}
            ${props.notes ? '<strong>Notes:</strong> ' + props.notes : ''}
        `;
    },

    getBookerOptions: function() {
        // Cette fonction devrait être alimentée par les données du serveur
        return '';
    },

    getStatusOptions: function(selectedStatus) {
        var statuses = bookingStatuses || {};
        var options = '';
        
        for (var statusId in statuses) {
            var selected = (selectedStatus == statusId) ? 'selected' : '';
            options += `<option value="${statusId}" ${selected}>${statuses[statusId].label}</option>`;
        }
        
        return options;
    },

    getHourOptions: function(selectedHour) {
        var options = '';
        for (var i = 0; i <= 23; i++) {
            var selected = (selectedHour == i) ? 'selected' : '';
            options += `<option value="${i}" ${selected}>${i}h00</option>`;
        }
        return options;
    },

    getPaymentStatusLabel: function(status) {
        var labels = {
            'pending': '<span class="label label-warning">En attente</span>',
            'authorized': '<span class="label label-info">Autorisé</span>',
            'captured': '<span class="label label-success">Capturé</span>',
            'cancelled': '<span class="label label-danger">Annulé</span>',
            'refunded': '<span class="label label-default">Remboursé</span>'
        };
        
        return labels[status] || '<span class="label label-default">Inconnu</span>';
    }
};

// Initialisation au chargement de la page
$(document).ready(function() {
    if (typeof bookingCalendarConfig !== 'undefined' && typeof ajaxUrl !== 'undefined') {
        ReservationCalendar.init();
    }
});