/**
 * Gestionnaire du calendrier des réservations
 * Gestion complète des réservations, validation, statuts et actions en lot
 */

document.addEventListener('DOMContentLoaded', function() {
    
    let calendar;
    let currentModal = null;
    let selectedReservations = [];
    let multiSelectMode = false;
    
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
        
        // Vérifier que ReservationCalendar est défini
        if (typeof ReservationCalendar === 'undefined') {
            console.error('ReservationCalendar non défini');
            return;
        }
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: ReservationCalendar.config.locale || 'fr',
            initialView: ReservationCalendar.config.default_view || 'timeGridWeek',
            initialDate: ReservationCalendar.currentDate,
            
            headerToolbar: {
                left: '',
                center: 'title',
                right: ''
            },
            
            height: 'auto',
            
            businessHours: ReservationCalendar.config.business_hours,
            
            selectable: true,
            selectMirror: false,
            
            eventClick: function(info) {
                handleEventClick(info);
            },
            
            select: function(info) {
                handleDateSelection(info);
            },
            
            eventDrop: function(info) {
                handleEventDrop(info);
            },
            
            eventResize: function(info) {
                handleEventResize(info);
            },
            
            events: function(info, successCallback, failureCallback) {
                loadReservations(info.startStr, info.endStr, successCallback, failureCallback);
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
        
        // Filtres
        document.getElementById('booker-filter')?.addEventListener('change', function() {
            calendar.refetchEvents();
        });
        
        document.getElementById('status-filter')?.addEventListener('change', function() {
            calendar.refetchEvents();
        });
        
        // Actions rapides
        document.getElementById('quick-validate-btn')?.addEventListener('click', quickValidateSelected);
        document.getElementById('quick-cancel-btn')?.addEventListener('click', quickCancelSelected);
        document.getElementById('quick-create-orders-btn')?.addEventListener('click', quickCreateOrders);
        document.getElementById('quick-send-reminders-btn')?.addEventListener('click', quickSendReminders);
        
        // Actions en lot
        document.getElementById('bulk-validate-btn')?.addEventListener('click', showBulkValidateModal);
        document.getElementById('bulk-cancel-btn')?.addEventListener('click', showBulkCancelModal);
        document.getElementById('bulk-send-notifications-btn')?.addEventListener('click', showBulkNotificationsModal);
        document.getElementById('export-reservations-btn')?.addEventListener('click', exportReservations);
        
        // Boutons de modal de réservation
        document.getElementById('validate-reservation-btn')?.addEventListener('click', validateCurrentReservation);
        document.getElementById('edit-reservation-btn')?.addEventListener('click', editCurrentReservation);
        document.getElementById('create-order-btn')?.addEventListener('click', createOrderForCurrent);
        document.getElementById('send-notification-btn')?.addEventListener('click', showSendNotificationModal);
        document.getElementById('cancel-reservation-btn')?.addEventListener('click', cancelCurrentReservation);
        
        // Boutons de formulaire
        document.getElementById('save-reservation-btn')?.addEventListener('click', saveReservationChanges);
        document.getElementById('cancel-edit-btn')?.addEventListener('click', cancelEdit);
        
        // Boutons d'exécution des actions en lot
        document.getElementById('execute-bulk-validate-btn')?.addEventListener('click', executeBulkValidate);
        document.getElementById('execute-bulk-cancel-btn')?.addEventListener('click', executeBulkCancel);
        document.getElementById('execute-send-notification-btn')?.addEventListener('click', executeSendNotification);
        
        // Gestion des modales
        setupModalHandlers();
        
        // Gestion du type de notification personnalisée
        document.getElementById('notification-type')?.addEventListener('change', function() {
            const customGroup = document.getElementById('custom-message-group');
            if (this.value === 'custom') {
                customGroup.style.display = 'block';
            } else {
                customGroup.style.display = 'none';
            }
        });
        
        // Mode multi-sélection avec Ctrl
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Control') {
                multiSelectMode = true;
            }
        });
        
        document.addEventListener('keyup', function(e) {
            if (e.key === 'Control') {
                multiSelectMode = false;
            }
        });
    }
    
    /**
     * Charger les réservations
     */
    function loadReservations(start, end, successCallback, failureCallback) {
        const bookerId = document.getElementById('booker-filter')?.value || '';
        const statusFilter = document.getElementById('status-filter')?.value || '';
        
        const params = {
            start: start,
            end: end,
            booker_id: bookerId,
            status: statusFilter
        };
        
        fetch(ReservationCalendar.ajaxUrls.get_reservations + '&' + new URLSearchParams(params))
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Erreur chargement réservations:', data.error);
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
        
        if (eventProps.type === 'reservation') {
            if (multiSelectMode) {
                toggleReservationSelection(info.event, eventProps.reservation_id);
            } else {
                // Désélectionner tout et sélectionner uniquement cette réservation
                clearSelection();
                showReservationDetails(eventProps.reservation_id);
            }
        }
    }
    
    /**
     * Basculer la sélection d'une réservation
     */
    function toggleReservationSelection(event, reservationId) {
        const index = selectedReservations.indexOf(reservationId);
        
        if (index > -1) {
            // Désélectionner
            selectedReservations.splice(index, 1);
            event.el.classList.remove('selected');
        } else {
            // Sélectionner
            selectedReservations.push(reservationId);
            event.el.classList.add('selected');
        }
        
        updateSelectionUI();
    }
    
    /**
     * Vider la sélection
     */
    function clearSelection() {
        selectedReservations = [];
        document.querySelectorAll('.fc-event.selected').forEach(el => {
            el.classList.remove('selected');
        });
        updateSelectionUI();
    }
    
    /**
     * Mettre à jour l'interface de sélection
     */
    function updateSelectionUI() {
        const count = selectedReservations.length;
        const quickActions = document.querySelector('.quick-actions');
        
        if (count > 0) {
            quickActions.style.display = 'block';
            // Mettre à jour le texte des boutons avec le nombre
            document.querySelectorAll('.quick-actions .btn').forEach(btn => {
                const text = btn.textContent.replace(/\(\d+\)/, '').trim();
                btn.textContent = text + ' (' + count + ')';
            });
        } else {
            quickActions.style.display = 'none';
        }
    }
    
    /**
     * Gestion de la sélection de dates
     */
    function handleDateSelection(info) {
        // Optionnel : créer une nouvelle réservation
        calendar.unselect();
    }
    
    /**
     * Gestion du déplacement d'événement
     */
    function handleEventDrop(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'reservation') {
            updateReservationDateTime(eventProps.reservation_id, info.event.start, info.event.end);
        }
    }
    
    /**
     * Gestion du redimensionnement d'événement
     */
    function handleEventResize(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'reservation') {
            updateReservationDateTime(eventProps.reservation_id, info.event.start, info.event.end);
        }
    }
    
    /**
     * Configuration des tooltips pour les événements
     */
    function setupEventTooltip(info) {
        const eventProps = info.event.extendedProps;
        
        if (eventProps.type === 'reservation') {
            const tooltip = `
                <strong>${eventProps.booking_reference}</strong><br>
                Client: ${eventProps.customer_name}<br>
                Email: ${eventProps.customer_email}<br>
                Prix: ${eventProps.total_price}€<br>
                Statut: ${getStatusLabel(eventProps.status)}<br>
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
        
        if (eventProps.type === 'reservation') {
            // Double-clic pour éditer
            info.el.addEventListener('dblclick', function() {
                showReservationDetails(eventProps.reservation_id);
            });
            
            // Clic droit pour menu contextuel
            info.el.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showContextMenu(e, eventProps.reservation_id, eventProps.status);
            });
        }
    }
    
    /**
     * Afficher les détails d'une réservation
     */
    function showReservationDetails(reservationId) {
        fetch(ReservationCalendar.ajaxUrls.get_reservation_details + '&id=' + reservationId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showReservationModal(data.data);
                } else {
                    showNotification('error', data.message || ReservationCalendar.messages.error_loading);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('error', ReservationCalendar.messages.error_loading);
            });
    }
    
    /**
     * Afficher la modal de réservation
     */
    function showReservationModal(reservation) {
        // Stocker l'ID de la réservation courante
        document.getElementById('reservation-id').value = reservation.id;
        
        // Construire le contenu des détails
        const detailsHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h5>Informations générales</h5>
                    <p><strong>Référence:</strong> ${reservation.booking_reference}</p>
                    <p><strong>Élément:</strong> ${reservation.booker_name}</p>
                    <p><strong>Période:</strong> ${formatDateTime(reservation.date_start)} - ${formatDateTime(reservation.date_end)}</p>
                    <p><strong>Prix total:</strong> ${reservation.total_price}€</p>
                    <p><strong>Statut:</strong> <span class="badge reservation-${reservation.status}">${getStatusLabel(reservation.status)}</span></p>
                    <p><strong>Paiement:</strong> <span class="badge">${getPaymentStatusLabel(reservation.payment_status)}</span></p>
                </div>
                <div class="col-md-6">
                    <h5>Informations client</h5>
                    <p><strong>Nom:</strong> ${reservation.customer_firstname} ${reservation.customer_lastname}</p>
                    <p><strong>Email:</strong> <a href="mailto:${reservation.customer_email}">${reservation.customer_email}</a></p>
                    <p><strong>Téléphone:</strong> ${reservation.customer_phone || 'Non renseigné'}</p>
                    ${reservation.order_reference ? '<p><strong>Commande:</strong> ' + reservation.order_reference + '</p>' : ''}
                </div>
            </div>
            ${reservation.notes ? '<div class="alert alert-info"><strong>Notes client:</strong> ' + reservation.notes + '</div>' : ''}
            ${reservation.admin_notes ? '<div class="alert alert-warning"><strong>Notes admin:</strong> ' + reservation.admin_notes + '</div>' : ''}
        `;
        
        document.getElementById('reservation-details').innerHTML = detailsHtml;
        
        // Pré-remplir le formulaire
        document.getElementById('reservation-status').value = reservation.status;
        document.getElementById('reservation-payment-status').value = reservation.payment_status;
        document.getElementById('reservation-admin-notes').value = reservation.admin_notes || '';
        
        // Ajuster les actions disponibles selon le statut
        updateActionButtons(reservation.status, reservation.payment_status);
        
        // Afficher la modal
        showModal('reservation-modal');
    }
    
    /**
     * Mettre à jour les boutons d'action selon le statut
     */
    function updateActionButtons(status, paymentStatus) {
        const validateBtn = document.getElementById('validate-reservation-btn');
        const cancelBtn = document.getElementById('cancel-reservation-btn');
        const createOrderBtn = document.getElementById('create-order-btn');
        
        // Validation disponible uniquement pour les réservations en attente
        if (validateBtn) {
            validateBtn.style.display = status === 'pending' ? 'inline-block' : 'none';
        }
        
        // Annulation disponible pour les statuts non finaux
        if (cancelBtn) {
            cancelBtn.style.display = ['pending', 'confirmed'].includes(status) ? 'inline-block' : 'none';
        }
        
        // Création de commande pour les réservations confirmées sans commande
        if (createOrderBtn) {
            createOrderBtn.style.display = status === 'confirmed' && paymentStatus === 'pending' ? 'inline-block' : 'none';
        }
    }
    
    /**
     * Valider la réservation courante
     */
    function validateCurrentReservation() {
        const reservationId = document.getElementById('reservation-id').value;
        
        if (!reservationId) {
            showNotification('error', 'Aucune réservation sélectionnée');
            return;
        }
        
        if (!confirm(ReservationCalendar.messages.confirm_validate)) {
            return;
        }
        
        const params = new URLSearchParams({
            id: reservationId,
            create_order: document.getElementById('bulk-validate-auto-create-order')?.checked || true,
            send_notification: document.getElementById('bulk-validate-send-notification')?.checked || true
        });
        
        fetch(ReservationCalendar.ajaxUrls.validate_reservation, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || ReservationCalendar.messages.success_validate);
                hideModal('reservation-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la validation');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la validation');
        });
    }
    
    /**
     * Annuler la réservation courante
     */
    function cancelCurrentReservation() {
        const reservationId = document.getElementById('reservation-id').value;
        
        if (!reservationId) {
            showNotification('error', 'Aucune réservation sélectionnée');
            return;
        }
        
        if (!confirm(ReservationCalendar.messages.confirm_cancel)) {
            return;
        }
        
        const reason = prompt('Motif d\'annulation:') || 'Annulation administrative';
        const notes = prompt('Notes additionnelles (optionnel):') || '';
        
        const params = new URLSearchParams({
            id: reservationId,
            reason: reason,
            notes: notes,
            send_notification: true,
            process_refund: false
        });
        
        fetch(ReservationCalendar.ajaxUrls.cancel_reservation, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || ReservationCalendar.messages.success_cancel);
                hideModal('reservation-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de l\'annulation');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de l\'annulation');
        });
    }
    
    /**
     * Créer une commande pour la réservation courante
     */
    function createOrderForCurrent() {
        const reservationId = document.getElementById('reservation-id').value;
        
        if (!reservationId) {
            showNotification('error', 'Aucune réservation sélectionnée');
            return;
        }
        
        const params = new URLSearchParams({ id: reservationId });
        
        fetch(ReservationCalendar.ajaxUrls.create_order, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || ReservationCalendar.messages.success_order_created);
                hideModal('reservation-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la création de commande');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la création de commande');
        });
    }
    
    /**
     * Éditer la réservation courante
     */
    function editCurrentReservation() {
        document.getElementById('reservation-details').style.display = 'none';
        document.getElementById('reservation-form').style.display = 'block';
        document.getElementById('reservation-actions').style.display = 'none';
        document.getElementById('reservation-form-actions').style.display = 'block';
    }
    
    /**
     * Annuler l'édition
     */
    function cancelEdit() {
        document.getElementById('reservation-details').style.display = 'block';
        document.getElementById('reservation-form').style.display = 'none';
        document.getElementById('reservation-actions').style.display = 'block';
        document.getElementById('reservation-form-actions').style.display = 'none';
    }
    
    /**
     * Sauvegarder les modifications de réservation
     */
    function saveReservationChanges() {
        const reservationId = document.getElementById('reservation-id').value;
        const status = document.getElementById('reservation-status').value;
        const paymentStatus = document.getElementById('reservation-payment-status').value;
        const adminNotes = document.getElementById('reservation-admin-notes').value;
        
        const params = new URLSearchParams({
            id: reservationId,
            status: status,
            payment_status: paymentStatus,
            admin_notes: adminNotes
        });
        
        fetch(ReservationCalendar.ajaxUrls.update_status, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || 'Réservation mise à jour');
                hideModal('reservation-modal');
                calendar.refetchEvents();
            } else {
                showNotification('error', data.message || 'Erreur lors de la mise à jour');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la mise à jour');
        });
    }
    
    /**
     * Actions rapides pour les réservations sélectionnées
     */
    function quickValidateSelected() {
        if (selectedReservations.length === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        showBulkValidateModal();
    }
    
    function quickCancelSelected() {
        if (selectedReservations.length === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        showBulkCancelModal();
    }
    
    function quickCreateOrders() {
        if (selectedReservations.length === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        // Créer les commandes pour toutes les réservations sélectionnées
        Promise.all(selectedReservations.map(id => {
            const params = new URLSearchParams({ id: id });
            return fetch(ReservationCalendar.ajaxUrls.create_order, {
                method: 'POST',
                body: params
            }).then(response => response.json());
        }))
        .then(results => {
            const successful = results.filter(r => r.success).length;
            showNotification('success', `${successful} commande(s) créée(s) avec succès`);
            calendar.refetchEvents();
            clearSelection();
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la création des commandes');
        });
    }
    
    function quickSendReminders() {
        if (selectedReservations.length === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        const params = new URLSearchParams({
            notification_type: 'reminder'
        });
        selectedReservations.forEach(id => params.append('ids[]', id));
        
        fetch(ReservationCalendar.ajaxUrls.bulk_send_notifications, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || 'Rappels envoyés avec succès');
                clearSelection();
            } else {
                showNotification('error', data.message || 'Erreur lors de l\'envoi des rappels');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de l\'envoi des rappels');
        });
    }
    
    /**
     * Modales d'actions en lot
     */
    function showBulkValidateModal() {
        const count = selectedReservations.length;
        if (count === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        document.getElementById('bulk-validation-count').textContent = 
            ReservationCalendar.messages.bulk_validate_count.replace('%d', count);
        
        showModal('bulk-validate-modal');
    }
    
    function showBulkCancelModal() {
        const count = selectedReservations.length;
        if (count === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        document.getElementById('bulk-cancel-count').textContent = 
            ReservationCalendar.messages.bulk_cancel_count.replace('%d', count);
        
        showModal('bulk-cancel-modal');
    }
    
    function showBulkNotificationsModal() {
        if (selectedReservations.length === 0) {
            showNotification('error', ReservationCalendar.messages.no_selection);
            return;
        }
        
        showModal('send-notification-modal');
    }
    
    function showSendNotificationModal() {
        const reservationId = document.getElementById('reservation-id').value;
        if (!reservationId) {
            showNotification('error', 'Aucune réservation sélectionnée');
            return;
        }
        
        document.getElementById('notification-reservation-id').value = reservationId;
        showModal('send-notification-modal');
    }
    
    /**
     * Exécution des actions en lot
     */
    function executeBulkValidate() {
        const autoCreateOrder = document.getElementById('bulk-validate-auto-create-order').checked;
        const sendNotification = document.getElementById('bulk-validate-send-notification').checked;
        
        const params = new URLSearchParams({
            auto_create_order: autoCreateOrder,
            send_notification: sendNotification
        });
        
        selectedReservations.forEach(id => params.append('ids[]', id));
        
        fetch(ReservationCalendar.ajaxUrls.bulk_validate, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || ReservationCalendar.messages.success_bulk_validate);
                hideModal('bulk-validate-modal');
                calendar.refetchEvents();
                clearSelection();
            } else {
                showNotification('error', data.message || 'Erreur lors de la validation en lot');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de la validation en lot');
        });
    }
    
    function executeBulkCancel() {
        const cancelReason = document.getElementById('bulk-cancel-reason').value;
        const cancelNotes = document.getElementById('bulk-cancel-notes').value;
        const sendNotification = document.getElementById('bulk-cancel-send-notification').checked;
        const processRefund = document.getElementById('bulk-cancel-process-refund').checked;
        
        if (!cancelReason) {
            showNotification('error', ReservationCalendar.messages.validation_required);
            return;
        }
        
        const params = new URLSearchParams({
            cancel_reason: cancelReason,
            cancel_notes: cancelNotes,
            send_notification: sendNotification,
            process_refund: processRefund
        });
        
        selectedReservations.forEach(id => params.append('ids[]', id));
        
        fetch(ReservationCalendar.ajaxUrls.bulk_cancel, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || ReservationCalendar.messages.success_bulk_cancel);
                hideModal('bulk-cancel-modal');
                calendar.refetchEvents();
                clearSelection();
            } else {
                showNotification('error', data.message || 'Erreur lors de l\'annulation en lot');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('error', 'Erreur lors de l\'annulation en lot');
        });
    }
    
    function executeSendNotification() {
        const notificationType = document.getElementById('notification-type').value;
        const customMessage = document.getElementById('notification-custom-message').value;
        const sendSms = document.getElementById('notification-send-sms').checked;
        
        if (!notificationType) {
            showNotification('error', ReservationCalendar.messages.validation_required);
            return;
        }
        
        if (notificationType === 'custom' && !customMessage) {
            showNotification('error', 'Message personnalisé requis');
            return;
        }
        
        // Notification individuelle ou en lot
        const reservationId = document.getElementById('notification-reservation-id').value;
        
        if (reservationId) {
            // Notification individuelle
            const params = new URLSearchParams({
                reservation_id: reservationId,
                notification_type: notificationType,
                custom_message: customMessage,
                send_sms: sendSms
            });
            
            fetch(ReservationCalendar.ajaxUrls.send_notification, {
                method: 'POST',
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message || ReservationCalendar.messages.success_notification_sent);
                    hideModal('send-notification-modal');
                } else {
                    showNotification('error', data.message || 'Erreur lors de l\'envoi');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('error', 'Erreur lors de l\'envoi');
            });
        } else {
            // Notification en lot
            const params = new URLSearchParams({
                notification_type: notificationType,
                custom_message: customMessage
            });
            
            selectedReservations.forEach(id => params.append('ids[]', id));
            
            fetch(ReservationCalendar.ajaxUrls.bulk_send_notifications, {
                method: 'POST',
                body: params
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message || 'Notifications envoyées avec succès');
                    hideModal('send-notification-modal');
                    clearSelection();
                } else {
                    showNotification('error', data.message || 'Erreur lors de l\'envoi en lot');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showNotification('error', 'Erreur lors de l\'envoi en lot');
            });
        }
    }
    
    /**
     * Exporter les réservations
     */
    function exportReservations() {
        const currentView = calendar.view;
        const start = formatDate(currentView.activeStart);
        const end = formatDate(currentView.activeEnd);
        
        const params = new URLSearchParams({
            start: start,
            end: end,
            format: 'csv'
        });
        
        const url = ReservationCalendar.ajaxUrls.export_reservations + '&' + params.toString();
        window.location.href = url;
    }
    
    /**
     * Mettre à jour la date/heure d'une réservation (drag & drop)
     */
    function updateReservationDateTime(reservationId, startDate, endDate) {
        const params = new URLSearchParams({
            id: reservationId,
            date_start: formatDateTime(startDate),
            date_end: formatDateTime(endDate)
        });
        
        fetch(ReservationCalendar.ajaxUrls.update_status, {
            method: 'POST',
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', 'Réservation déplacée avec succès');
            } else {
                showNotification('error', data.message || 'Erreur lors du déplacement');
                calendar.refetchEvents(); // Revert changes
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            calendar.refetchEvents(); // Revert changes
        });
    }
    
    /**
     * Afficher un menu contextuel
     */
    function showContextMenu(event, reservationId, status) {
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
        
        const actions = [];
        
        // Actions selon le statut
        if (status === 'pending') {
            actions.push({ label: 'Valider', action: () => validateReservation(reservationId) });
        }
        
        actions.push({ label: 'Voir détails', action: () => showReservationDetails(reservationId) });
        actions.push({ label: 'Envoyer notification', action: () => sendNotificationToReservation(reservationId) });
        
        if (['pending', 'confirmed'].includes(status)) {
            actions.push({ label: 'Annuler', action: () => cancelReservation(reservationId) });
        }
        
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
    
    function formatDateTime(date) {
        const d = new Date(date);
        return d.getFullYear() + '-' + 
               (d.getMonth() + 1).toString().padStart(2, '0') + '-' + 
               d.getDate().toString().padStart(2, '0') + ' ' +
               d.getHours().toString().padStart(2, '0') + ':' + 
               d.getMinutes().toString().padStart(2, '0') + ':00';
    }
    
    function getStatusLabel(status) {
        const labels = {
            'pending': 'En attente',
            'confirmed': 'Confirmé',
            'paid': 'Payé',
            'cancelled': 'Annulé',
            'completed': 'Terminé',
            'refunded': 'Remboursé'
        };
        return labels[status] || status;
    }
    
    function getPaymentStatusLabel(status) {
        const labels = {
            'pending': 'En attente',
            'authorized': 'Autorisé',
            'captured': 'Capturé',
            'cancelled': 'Annulé',
            'refunded': 'Remboursé'
        };
        return labels[status] || status;
    }
    
    // Actions du menu contextuel
    function validateReservation(reservationId) {
        document.getElementById('reservation-id').value = reservationId;
        validateCurrentReservation();
    }
    
    function cancelReservation(reservationId) {
        document.getElementById('reservation-id').value = reservationId;
        cancelCurrentReservation();
    }
    
    function sendNotificationToReservation(reservationId) {
        document.getElementById('notification-reservation-id').value = reservationId;
        showModal('send-notification-modal');
    }
});
