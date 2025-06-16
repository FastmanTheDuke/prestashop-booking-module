/**
 * JavaScript avancé pour le système de réservations avec caution
 * Version 2.1.4 - Gestion interactive du calendrier et intégration Stripe
 */

class BookingManager {
    constructor(config) {
        this.config = {
            stripePublicKey: '',
            ajaxUrl: '',
            bookerId: null,
            currentStep: 1,
            selectedDate: null,
            selectedTimeSlot: null,
            depositRequired: true,
            ...config
        };
        
        // Instances Stripe
        this.stripe = null;
        this.elements = null;
        this.cardElement = null;
        
        // État de l'application
        this.state = {
            loading: false,
            availability: {},
            bookingData: {},
            errors: []
        };
        
        this.init();
    }
    
    /**
     * Initialisation du gestionnaire de réservations
     */
    init() {
        console.log('🚀 Initialisation du BookingManager v2.1.4');
        
        // Initialiser Stripe si nécessaire
        if (this.config.stripePublicKey && typeof Stripe !== 'undefined') {
            this.initStripe();
        }
        
        // Initialiser le calendrier
        this.initCalendar();
        
        // Initialiser les événements
        this.initEventListeners();
        
        // Valider l'état initial
        this.validateCurrentStep();
        
        console.log('✅ BookingManager initialisé avec succès');
    }
    
    /**
     * Initialiser Stripe Elements
     */
    initStripe() {
        try {
            this.stripe = Stripe(this.config.stripePublicKey);
            this.elements = this.stripe.elements();
            
            // Configuration du style des éléments
            const elementStyles = {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    fontFamily: '"Segoe UI", Roboto, sans-serif',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                    iconColor: '#666EE8',
                },
                invalid: {
                    color: '#e74c3c',
                    iconColor: '#e74c3c',
                },
                complete: {
                    color: '#27ae60',
                    iconColor: '#27ae60',
                },
            };
            
            // Créer l'élément carte
            this.cardElement = this.elements.create('card', {
                style: elementStyles,
                hidePostalCode: true
            });
            
            console.log('💳 Stripe Elements initialisé');
        } catch (error) {
            console.error('❌ Erreur initialisation Stripe:', error);
            this.showError('Erreur lors de l\'initialisation du système de paiement');
        }
    }
    
    /**
     * Initialiser le calendrier FullCalendar
     */
    initCalendar() {
        const calendarEl = document.getElementById('availability-calendar');
        if (!calendarEl) return;
        
        // Configuration FullCalendar
        const calendarConfig = {
            initialView: 'dayGridMonth',
            locale: 'fr',
            firstDay: 1, // Lundi
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5, 6], // Lundi à samedi
                startTime: '08:00',
                endTime: '20:00'
            },
            selectConstraint: 'businessHours',
            validRange: {
                start: new Date() // Pas de dates passées
            },
            events: (info, successCallback, failureCallback) => {
                this.loadAvailability(info.startStr, info.endStr)
                    .then(events => successCallback(events))
                    .catch(error => {
                        console.error('Erreur chargement disponibilités:', error);
                        failureCallback(error);
                    });
            },
            dateClick: (info) => {
                this.selectDate(info.dateStr);
            },
            eventClick: (info) => {
                this.handleEventClick(info);
            },
            dayCellDidMount: (info) => {
                this.customizeDayCell(info);
            }
        };
        
        // Initialiser le calendrier
        this.calendar = new FullCalendar.Calendar(calendarEl, calendarConfig);
        this.calendar.render();
        
        console.log('📅 Calendrier initialisé');
    }
    
    /**
     * Charger les disponibilités depuis le serveur
     */
    async loadAvailability(start, end) {
        this.setLoading(true);
        
        try {
            const response = await this.ajaxRequest('getAvailability', {
                id_booker: this.config.bookerId,
                start: start,
                end: end
            });
            
            if (response.success) {
                this.state.availability = response.availability || {};
                return this.formatAvailabilityEvents(response.availability);
            } else {
                throw new Error(response.error || 'Erreur chargement disponibilités');
            }
        } catch (error) {
            console.error('❌ Erreur loadAvailability:', error);
            this.showError('Impossible de charger les disponibilités');
            return [];
        } finally {
            this.setLoading(false);
        }
    }
    
    /**
     * Formater les données de disponibilité pour FullCalendar
     */
    formatAvailabilityEvents(availability) {
        const events = [];
        
        Object.entries(availability).forEach(([date, slots]) => {
            slots.forEach(slot => {
                events.push({
                    id: `slot-${date}-${slot.hour_from}-${slot.hour_to}`,
                    title: slot.available ? 
                        `Disponible (${slot.hour_from}h-${slot.hour_to}h)` : 
                        `Réservé (${slot.hour_from}h-${slot.hour_to}h)`,
                    start: `${date}T${String(slot.hour_from).padStart(2, '0')}:00:00`,
                    end: `${date}T${String(slot.hour_to).padStart(2, '0')}:00:00`,
                    className: slot.available ? 'fc-event-available' : 'fc-event-booked',
                    extendedProps: {
                        available: slot.available,
                        price: slot.price || 0,
                        date: date,
                        hour_from: slot.hour_from,
                        hour_to: slot.hour_to
                    }
                });
            });
        });
        
        return events;
    }
    
    /**
     * Personnaliser l'affichage des cellules de jour
     */
    customizeDayCell(info) {
        const dateStr = info.date.toISOString().split('T')[0];
        const availability = this.state.availability[dateStr];
        
        if (availability) {
            const availableSlots = availability.filter(slot => slot.available).length;
            const totalSlots = availability.length;
            
            if (availableSlots === 0) {
                info.el.classList.add('fc-day-unavailable');
            } else if (availableSlots < totalSlots) {
                info.el.classList.add('fc-day-partial');
            } else {
                info.el.classList.add('fc-day-available');
            }
            
            // Ajouter un indicateur
            const indicator = document.createElement('div');
            indicator.className = 'availability-indicator';
            indicator.textContent = `${availableSlots}/${totalSlots}`;
            info.el.appendChild(indicator);
        }
    }
    
    /**
     * Sélectionner une date
     */
    selectDate(dateStr) {
        console.log('📅 Date sélectionnée:', dateStr);
        
        this.config.selectedDate = dateStr;
        
        // Mettre à jour l'input caché
        const dateInput = document.getElementById('selected_date');
        if (dateInput) {
            dateInput.value = dateStr;
        }
        
        // Afficher la sélection d'heures
        this.showTimeSelection(dateStr);
        
        // Mettre à jour le récapitulatif
        this.updateSummary();
    }
    
    /**
     * Afficher la sélection d'heures pour une date
     */
    showTimeSelection(dateStr) {
        const timeSelection = document.querySelector('.time-selection');
        if (!timeSelection) return;
        
        const availability = this.state.availability[dateStr];
        if (!availability || availability.length === 0) {
            this.showError('Aucune disponibilité pour cette date');
            return;
        }
        
        // Filtrer les créneaux disponibles
        const availableSlots = availability.filter(slot => slot.available);
        if (availableSlots.length === 0) {
            this.showError('Aucun créneau disponible pour cette date');
            return;
        }
        
        // Remplir les sélecteurs d'heures
        this.populateTimeSelectors(availableSlots);
        
        // Afficher la section
        timeSelection.style.display = 'block';
        timeSelection.classList.add('show');
        
        // Scroll vers la section
        timeSelection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    /**
     * Remplir les sélecteurs d'heures
     */
    populateTimeSelectors(availableSlots) {
        const hourFromSelect = document.getElementById('hour_from');
        const hourToSelect = document.getElementById('hour_to');
        
        if (!hourFromSelect || !hourToSelect) return;
        
        // Réinitialiser les options
        hourFromSelect.innerHTML = '<option value="">-- Choisir l\'heure de début --</option>';
        hourToSelect.innerHTML = '<option value="">-- Choisir l\'heure de fin --</option>';
        
        // Obtenir toutes les heures disponibles
        const availableHours = new Set();
        availableSlots.forEach(slot => {
            for (let h = slot.hour_from; h <= slot.hour_to; h++) {
                availableHours.add(h);
            }
        });
        
        // Ajouter les options d'heures de début
        Array.from(availableHours).sort((a, b) => a - b).forEach(hour => {
            const option = document.createElement('option');
            option.value = hour;
            option.textContent = `${hour}:00`;
            hourFromSelect.appendChild(option);
        });
    }
    
    /**
     * Valider et mettre à jour les heures de fin
     */
    updateEndTimeOptions() {
        const hourFromSelect = document.getElementById('hour_from');
        const hourToSelect = document.getElementById('hour_to');
        
        if (!hourFromSelect || !hourToSelect || !hourFromSelect.value) return;
        
        const startHour = parseInt(hourFromSelect.value);
        const dateStr = this.config.selectedDate;
        const availability = this.state.availability[dateStr];
        
        // Réinitialiser les options de fin
        hourToSelect.innerHTML = '<option value="">-- Choisir l\'heure de fin --</option>';
        
        // Trouver les heures de fin possibles
        const possibleEndHours = new Set();
        availability.forEach(slot => {
            if (slot.available && slot.hour_from <= startHour && slot.hour_to > startHour) {
                for (let h = startHour + 1; h <= slot.hour_to; h++) {
                    possibleEndHours.add(h);
                }
            }
        });
        
        // Ajouter les options
        Array.from(possibleEndHours).sort((a, b) => a - b).forEach(hour => {
            const option = document.createElement('option');
            option.value = hour;
            option.textContent = `${hour}:00`;
            hourToSelect.appendChild(option);
        });
        
        this.validateTimeSelection();
    }
    
    /**
     * Valider la sélection d'heures
     */
    validateTimeSelection() {
        const hourFrom = document.getElementById('hour_from');
        const hourTo = document.getElementById('hour_to');
        const continueBtn = document.getElementById('continue-step1');
        
        if (!hourFrom || !hourTo || !continueBtn) return;
        
        const isValid = hourFrom.value && hourTo.value && 
                       parseInt(hourTo.value) > parseInt(hourFrom.value);
        
        continueBtn.disabled = !isValid;
        
        if (isValid) {
            this.config.selectedTimeSlot = {
                from: parseInt(hourFrom.value),
                to: parseInt(hourTo.value)
            };
            this.calculatePrice();
            this.updateSummary();
        }
    }
    
    /**
     * Calculer le prix de la réservation
     */
    async calculatePrice() {
        if (!this.config.selectedDate || !this.config.selectedTimeSlot) return;
        
        try {
            const response = await this.ajaxRequest('calculatePrice', {
                id_booker: this.config.bookerId,
                date: this.config.selectedDate,
                hour_from: this.config.selectedTimeSlot.from,
                hour_to: this.config.selectedTimeSlot.to
            });
            
            if (response.success) {
                this.state.priceCalculation = response.calculation;
                this.updateSummary();
            }
        } catch (error) {
            console.error('❌ Erreur calcul prix:', error);
        }
    }
    
    /**
     * Mettre à jour le récapitulatif
     */
    updateSummary() {
        const summary = document.querySelector('.booking-summary');
        if (!summary) return;
        
        // Mettre à jour les détails de réservation
        this.updateSummarySection('.booking-details', {
            date: this.config.selectedDate,
            timeSlot: this.config.selectedTimeSlot,
            customer: this.state.bookingData.customer
        });
        
        // Mettre à jour le calcul des prix
        if (this.state.priceCalculation) {
            this.updateSummarySection('.price-breakdown', this.state.priceCalculation);
        }
    }
    
    /**
     * Mettre à jour une section du récapitulatif
     */
    updateSummarySection(selector, data) {
        const section = document.querySelector(selector);
        if (!section || !data) return;
        
        // Logique de mise à jour selon le type de section
        if (selector === '.booking-details') {
            this.updateBookingDetails(section, data);
        } else if (selector === '.price-breakdown') {
            this.updatePriceBreakdown(section, data);
        }
    }
    
    /**
     * Mettre à jour les détails de réservation dans le récapitulatif
     */
    updateBookingDetails(section, data) {
        const rows = section.querySelectorAll('.detail-row');
        
        rows.forEach(row => {
            const label = row.querySelector('.label').textContent.toLowerCase();
            const valueEl = row.querySelector('.value');
            
            if (label.includes('date') && data.date) {
                valueEl.textContent = new Date(data.date).toLocaleDateString('fr-FR');
            } else if (label.includes('horaire') && data.timeSlot) {
                valueEl.textContent = `${data.timeSlot.from}h - ${data.timeSlot.to}h`;
            } else if (label.includes('durée') && data.timeSlot) {
                valueEl.textContent = `${data.timeSlot.to - data.timeSlot.from}h`;
            } else if (label.includes('client') && data.customer) {
                valueEl.textContent = `${data.customer.firstname} ${data.customer.lastname}`;
            }
        });
    }
    
    /**
     * Mettre à jour le calcul des prix dans le récapitulatif
     */
    updatePriceBreakdown(section, calculation) {
        const rows = section.querySelectorAll('.detail-row');
        
        rows.forEach(row => {
            const label = row.querySelector('.label').textContent.toLowerCase();
            const valueEl = row.querySelector('.value');
            
            if (label.includes('base') && calculation.base_price) {
                valueEl.textContent = this.formatPrice(calculation.base_price);
            } else if (label.includes('frais') && calculation.extra_fees) {
                valueEl.textContent = this.formatPrice(calculation.extra_fees);
            } else if (label.includes('total') && calculation.total_price) {
                valueEl.textContent = this.formatPrice(calculation.total_price);
            }
        });
        
        // Afficher la caution si nécessaire
        if (this.config.depositRequired && calculation.deposit_amount) {
            this.showDepositInfo(calculation.deposit_amount);
        }
    }
    
    /**
     * Afficher les informations de caution
     */
    showDepositInfo(depositAmount) {
        let depositRow = document.querySelector('.detail-row.deposit');
        
        if (!depositRow) {
            depositRow = document.createElement('div');
            depositRow.className = 'detail-row deposit';
            depositRow.innerHTML = `
                <span class="label">Caution (préautorisée) :</span>
                <span class="value text-warning"></span>
            `;
            
            const priceBreakdown = document.querySelector('.price-breakdown');
            if (priceBreakdown) {
                priceBreakdown.appendChild(depositRow);
            }
        }
        
        const valueEl = depositRow.querySelector('.value');
        if (valueEl) {
            valueEl.textContent = this.formatPrice(depositAmount);
        }
    }
    
    /**
     * Monter l'élément carte Stripe
     */
    mountCardElement() {
        const cardContainer = document.getElementById('card-element');
        if (!cardContainer || !this.cardElement) return;
        
        try {
            this.cardElement.mount('#card-element');
            
            // Gérer les événements de l'élément carte
            this.cardElement.on('change', (event) => {
                this.handleCardChange(event);
            });
            
            this.cardElement.on('ready', () => {
                console.log('💳 Élément carte prêt');
            });
            
            console.log('💳 Élément carte monté');
        } catch (error) {
            console.error('❌ Erreur montage carte:', error);
            this.showError('Erreur lors de l\'initialisation du formulaire de carte');
        }
    }
    
    /**
     * Gérer les changements de l'élément carte
     */
    handleCardChange(event) {
        const displayError = document.getElementById('card-errors');
        const submitButton = document.getElementById('submit-deposit');
        
        if (event.error) {
            displayError.textContent = event.error.message;
            if (submitButton) submitButton.disabled = true;
        } else {
            displayError.textContent = '';
            if (submitButton) submitButton.disabled = !event.complete;
        }
        
        // Mettre à jour l'état visuel
        const cardContainer = document.getElementById('card-element');
        if (cardContainer) {
            cardContainer.classList.toggle('error', !!event.error);
            cardContainer.classList.toggle('complete', event.complete);
        }
    }
    
    /**
     * Traiter la soumission du formulaire de caution
     */
    async processDepositForm(formData) {
        const submitButton = document.getElementById('submit-deposit');
        const submitText = document.getElementById('submit-text');
        const submitSpinner = document.getElementById('submit-spinner');
        
        // État de chargement
        this.setButtonLoading(submitButton, submitText, submitSpinner, true);
        
        try {
            // Créer le Setup Intent côté serveur
            const setupResponse = await this.ajaxRequest('createDepositSetup', formData);
            
            if (!setupResponse.success) {
                throw new Error(setupResponse.error || 'Erreur création Setup Intent');
            }
            
            // Confirmer avec Stripe
            const { error, setupIntent } = await this.stripe.confirmCardSetup(
                setupResponse.client_secret,
                {
                    payment_method: {
                        card: this.cardElement,
                        billing_details: {
                            name: formData.cardholder_name || '',
                            email: formData.customer_email || '',
                        },
                    },
                }
            );
            
            if (error) {
                throw new Error(error.message);
            }
            
            // Finaliser la réservation
            await this.finalizeBooking(setupIntent);
            
        } catch (error) {
            console.error('❌ Erreur traitement caution:', error);
            this.showError(error.message);
            this.setButtonLoading(submitButton, submitText, submitSpinner, false);
        }
    }
    
    /**
     * Finaliser la réservation après autorisation de la caution
     */
    async finalizeBooking(setupIntent) {
        try {
            const response = await this.ajaxRequest('finalizeBooking', {
                setup_intent_id: setupIntent.id,
                payment_method_id: setupIntent.payment_method,
                booking_data: this.state.bookingData
            });
            
            if (response.success) {
                // Rediriger vers la confirmation
                this.goToStep(4, { reservation_id: response.reservation_id });
            } else {
                throw new Error(response.error || 'Erreur finalisation réservation');
            }
        } catch (error) {
            console.error('❌ Erreur finalisation:', error);
            this.showError(error.message);
        }
    }
    
    /**
     * Gérer l'état de chargement d'un bouton
     */
    setButtonLoading(button, textEl, spinnerEl, loading) {
        if (!button) return;
        
        button.disabled = loading;
        
        if (textEl) {
            textEl.textContent = loading ? 'Traitement en cours...' : 'Autoriser la caution';
        }
        
        if (spinnerEl) {
            spinnerEl.style.display = loading ? 'inline-block' : 'none';
        }
    }
    
    /**
     * Naviguer vers une étape
     */
    goToStep(step, additionalData = {}) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = this.config.formAction || window.location.href;
        
        // Données de base
        const formData = {
            step: step,
            booking_data: JSON.stringify({
                ...this.state.bookingData,
                ...additionalData
            }),
            ...additionalData
        };
        
        // Créer les inputs cachés
        Object.entries(formData).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = typeof value === 'object' ? JSON.stringify(value) : value;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
    
    /**
     * Valider l'étape actuelle
     */
    validateCurrentStep() {
        switch (this.config.currentStep) {
            case 1:
                this.validateStep1();
                break;
            case 2:
                this.validateStep2();
                break;
            case 3:
                this.validateStep3();
                break;
            case 4:
                this.validateStep4();
                break;
        }
    }
    
    /**
     * Valider l'étape 1 (sélection)
     */
    validateStep1() {
        // Réactiver les événements de sélection d'heures
        const hourFrom = document.getElementById('hour_from');
        const hourTo = document.getElementById('hour_to');
        
        if (hourFrom && hourTo) {
            hourFrom.addEventListener('change', () => this.updateEndTimeOptions());
            hourTo.addEventListener('change', () => this.validateTimeSelection());
        }
    }
    
    /**
     * Valider l'étape 2 (informations)
     */
    validateStep2() {
        const form = document.getElementById('booking-form-step2');
        if (!form) return;
        
        // Validation en temps réel
        form.addEventListener('input', () => {
            this.validateFormStep2();
        });
        
        // Pré-remplir avec les données existantes
        this.prefillCustomerData();
    }
    
    /**
     * Valider l'étape 3 (caution)
     */
    validateStep3() {
        // Monter l'élément carte si pas déjà fait
        if (this.cardElement && !document.querySelector('#card-element iframe')) {
            this.mountCardElement();
        }
        
        // Gérer la soumission du formulaire
        const form = document.getElementById('booking-form-step3');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleDepositFormSubmit(e);
            });
        }
    }
    
    /**
     * Valider l'étape 4 (confirmation)
     */
    validateStep4() {
        // Rien à valider, juste afficher la confirmation
        console.log('✅ Réservation confirmée');
    }
    
    /**
     * Gérer la soumission du formulaire de caution
     */
    async handleDepositFormSubmit(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData.entries());
        
        // Ajouter les données de réservation
        data.booking_data = this.state.bookingData;
        
        await this.processDepositForm(data);
    }
    
    /**
     * Initialiser les événements
     */
    initEventListeners() {
        // Événements globaux
        document.addEventListener('DOMContentLoaded', () => {
            this.onDOMReady();
        });
        
        // Événements de navigation
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-back')) {
                this.handleBackNavigation(e);
            }
        });
        
        // Événements de formulaire
        document.addEventListener('submit', (e) => {
            if (e.target.id.startsWith('booking-form-')) {
                this.handleFormSubmit(e);
            }
        });
    }
    
    /**
     * Actions à effectuer quand le DOM est prêt
     */
    onDOMReady() {
        // Réinitialiser selon l'étape actuelle
        this.validateCurrentStep();
        
        // Charger les données sauvegardées si présentes
        this.loadSavedData();
        
        // Mettre à jour le récapitulatif
        this.updateSummary();
    }
    
    /**
     * Charger les données sauvegardées
     */
    loadSavedData() {
        // Récupérer depuis les inputs cachés ou sessionStorage
        const bookingDataInput = document.querySelector('input[name="booking_data"]');
        if (bookingDataInput && bookingDataInput.value) {
            try {
                this.state.bookingData = JSON.parse(bookingDataInput.value);
            } catch (error) {
                console.warn('Erreur parsing données de réservation:', error);
            }
        }
    }
    
    /**
     * Effectuer une requête AJAX
     */
    async ajaxRequest(action, data = {}) {
        const requestData = {
            action: action,
            ajax: true,
            ...data
        };
        
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('❌ Erreur AJAX:', error);
            throw error;
        }
    }
    
    /**
     * Afficher un message d'erreur
     */
    showError(message) {
        console.error('❌', message);
        
        // Créer ou mettre à jour l'alerte d'erreur
        let alertContainer = document.querySelector('.alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.className = 'alert-container';
            document.querySelector('.booking-container').prepend(alertContainer);
        }
        
        alertContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Erreur :</strong> ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        // Auto-masquer après 5 secondes
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }
    
    /**
     * Afficher un message de succès
     */
    showSuccess(message) {
        console.log('✅', message);
        
        // Similaire à showError mais avec la classe success
        let alertContainer = document.querySelector('.alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.className = 'alert-container';
            document.querySelector('.booking-container').prepend(alertContainer);
        }
        
        alertContainer.innerHTML = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Succès :</strong> ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
    }
    
    /**
     * Gérer l'état de chargement global
     */
    setLoading(loading) {
        this.state.loading = loading;
        
        const container = document.querySelector('.booking-container');
        if (container) {
            container.classList.toggle('loading', loading);
        }
        
        // Désactiver/réactiver les boutons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            if (loading) {
                btn.dataset.originalDisabled = btn.disabled;
                btn.disabled = true;
            } else {
                btn.disabled = btn.dataset.originalDisabled === 'true';
            }
        });
    }
    
    /**
     * Formater un prix
     */
    formatPrice(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
    
    /**
     * Déboguer l'état actuel
     */
    debug() {
        console.group('🔍 Debug BookingManager');
        console.log('Config:', this.config);
        console.log('State:', this.state);
        console.log('Stripe:', this.stripe);
        console.log('Calendar:', this.calendar);
        console.groupEnd();
    }
}

// Initialisation automatique quand le script est chargé
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier si on est sur une page de réservation
    const bookingContainer = document.querySelector('.booking-container');
    if (!bookingContainer) return;
    
    // Récupérer la configuration depuis les données de la page
    const config = {
        stripePublicKey: window.bookingConfig?.stripePublicKey || '',
        ajaxUrl: window.bookingConfig?.ajaxUrl || '',
        bookerId: window.bookingConfig?.bookerId || null,
        currentStep: window.bookingConfig?.currentStep || 1,
        formAction: window.bookingConfig?.formAction || '',
        depositRequired: window.bookingConfig?.depositRequired !== false
    };
    
    // Initialiser le gestionnaire de réservations
    window.bookingManager = new BookingManager(config);
    
    // Exposer pour debug
    if (window.bookingConfig?.debug) {
        window.debug = () => window.bookingManager.debug();
        console.log('🐛 Mode debug activé. Tapez debug() dans la console.');
    }
});

// Fonctions utilitaires globales pour compatibilité
function goToStep(step) {
    if (window.bookingManager) {
        window.bookingManager.goToStep(step);
    }
}

function selectDate(dateStr) {
    if (window.bookingManager) {
        window.bookingManager.selectDate(dateStr);
    }
}

// Export pour utilisation en module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BookingManager;
}
