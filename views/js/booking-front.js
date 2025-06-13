/**
 * Booking Front-end JavaScript
 * Interface utilisateur pour le système de réservation
 */

class BookingFrontend {
    constructor() {
        this.selectedDates = [];
        this.selectedTimeSlots = [];
        this.currentBooker = null;
        this.currentMonth = new Date();
        this.bookerData = {};
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadCalendar();
        this.initTimeSlotSelector();
    }

    bindEvents() {
        // Navigation du calendrier
        $(document).on('click', '.booking-nav-prev', () => this.previousMonth());
        $(document).on('click', '.booking-nav-next', () => this.nextMonth());
        
        // Sélection de booker
        $(document).on('change', '.booker-selector', (e) => this.selectBooker(e.target.value));
        
        // Sélection de dates
        $(document).on('click', '.calendar-day.available', (e) => this.toggleDateSelection(e));
        
        // Sélection de créneaux horaires
        $(document).on('click', '.time-slot.available', (e) => this.toggleTimeSlot(e));
        
        // Soumission du formulaire
        $(document).on('click', '.booking-submit', () => this.submitBooking());
        
        // Modal de confirmation
        $(document).on('click', '.booking-confirm', () => this.confirmBooking());
        $(document).on('click', '.booking-cancel', () => this.cancelBooking());
    }

    loadCalendar() {
        const monthStr = this.currentMonth.getFullYear() + '-' + 
                        String(this.currentMonth.getMonth() + 1).padStart(2, '0');
        
        $.ajax({
            url: bookingAjaxUrl,
            type: 'POST',
            data: {
                action: 'getCalendarData',
                month: monthStr,
                id_booker: this.currentBooker
            },
            success: (response) => {
                if (response.success) {
                    this.renderCalendar(response.data);
                } else {
                    this.showError(response.error || 'Erreur lors du chargement du calendrier');
                }
            },
            error: () => this.showError('Erreur de connexion')
        });
    }

    renderCalendar(data) {
        const calendar = $('.booking-calendar');
        calendar.empty();
        
        // En-tête du calendrier
        const header = this.createCalendarHeader();
        calendar.append(header);
        
        // Grille du calendrier
        const grid = this.createCalendarGrid(data);
        calendar.append(grid);
        
        // Mettre à jour le sélecteur de créneaux
        this.updateTimeSlots(data.timeSlots);
    }

    createCalendarHeader() {
        const monthNames = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        
        const monthYear = monthNames[this.currentMonth.getMonth()] + ' ' + 
                         this.currentMonth.getFullYear();
        
        return `
            <div class="calendar-header">
                <button class="booking-nav-prev" type="button">
                    <i class="material-icons">chevron_left</i>
                </button>
                <h3 class="calendar-title">${monthYear}</h3>
                <button class="booking-nav-next" type="button">
                    <i class="material-icons">chevron_right</i>
                </button>
            </div>
            <div class="calendar-weekdays">
                <div class="weekday">Lun</div>
                <div class="weekday">Mar</div>
                <div class="weekday">Mer</div>
                <div class="weekday">Jeu</div>
                <div class="weekday">Ven</div>
                <div class="weekday">Sam</div>
                <div class="weekday">Dim</div>
            </div>
        `;
    }

    createCalendarGrid(data) {
        const firstDay = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth(), 1);
        const lastDay = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth() + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - (firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1));
        
        let html = '<div class="calendar-grid">';
        let currentDate = new Date(startDate);
        
        for (let week = 0; week < 6; week++) {
            html += '<div class="calendar-week">';
            
            for (let day = 0; day < 7; day++) {
                const dateStr = currentDate.toISOString().split('T')[0];
                const isCurrentMonth = currentDate.getMonth() === this.currentMonth.getMonth();
                const isToday = this.isToday(currentDate);
                const isSelected = this.selectedDates.includes(dateStr);
                const availability = data.availabilities[dateStr] || {};
                const isAvailable = availability.available && isCurrentMonth;
                const isPast = currentDate < new Date().setHours(0,0,0,0);
                
                let dayClass = 'calendar-day';
                if (!isCurrentMonth) dayClass += ' other-month';
                if (isToday) dayClass += ' today';
                if (isSelected) dayClass += ' selected';
                if (isAvailable && !isPast) dayClass += ' available';
                if (isPast) dayClass += ' past';
                if (availability.hasReservations) dayClass += ' has-reservations';
                
                html += `
                    <div class="${dayClass}" data-date="${dateStr}">
                        <span class="day-number">${currentDate.getDate()}</span>
                        ${availability.slotsCount ? `<span class="slots-count">${availability.slotsCount} créneaux</span>` : ''}
                    </div>
                `;
                
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            html += '</div>';
            
            if (currentDate.getMonth() !== this.currentMonth.getMonth()) break;
        }
        
        html += '</div>';
        return html;
    }

    updateTimeSlots(timeSlots) {
        const container = $('.time-slots-container');
        container.empty();
        
        if (!timeSlots || timeSlots.length === 0) {
            container.html('<p class="no-slots">Sélectionnez une date pour voir les créneaux disponibles</p>');
            return;
        }
        
        let html = '<div class="time-slots-grid">';
        timeSlots.forEach(slot => {
            const isSelected = this.selectedTimeSlots.some(s => 
                s.date === slot.date && s.hour_from === slot.hour_from
            );
            const isAvailable = slot.available && !slot.reserved;
            
            let slotClass = 'time-slot';
            if (isSelected) slotClass += ' selected';
            if (isAvailable) slotClass += ' available';
            if (slot.reserved) slotClass += ' reserved';
            
            html += `
                <div class="${slotClass}" 
                     data-date="${slot.date}" 
                     data-hour-from="${slot.hour_from}" 
                     data-hour-to="${slot.hour_to}">
                    <span class="slot-time">${this.formatTime(slot.hour_from)} - ${this.formatTime(slot.hour_to)}</span>
                    ${slot.price ? `<span class="slot-price">${slot.price}€</span>` : ''}
                </div>
            `;
        });
        html += '</div>';
        
        container.html(html);
    }

    toggleDateSelection(event) {
        const date = $(event.currentTarget).data('date');
        const dateIndex = this.selectedDates.indexOf(date);
        
        if (dateIndex === -1) {
            // Mode sélection multiple ou simple selon configuration
            if (!this.isMultiSelectEnabled()) {
                this.selectedDates = [date];
            } else {
                this.selectedDates.push(date);
            }
        } else {
            this.selectedDates.splice(dateIndex, 1);
        }
        
        this.updateCalendarSelection();
        this.loadTimeSlotsForSelectedDates();
        this.updateBookingSummary();
    }

    toggleTimeSlot(event) {
        const $slot = $(event.currentTarget);
        const slotData = {
            date: $slot.data('date'),
            hour_from: $slot.data('hour-from'),
            hour_to: $slot.data('hour-to')
        };
        
        const slotIndex = this.selectedTimeSlots.findIndex(s => 
            s.date === slotData.date && s.hour_from === slotData.hour_from
        );
        
        if (slotIndex === -1) {
            this.selectedTimeSlots.push(slotData);
        } else {
            this.selectedTimeSlots.splice(slotIndex, 1);
        }
        
        this.updateTimeSlotsSelection();
        this.updateBookingSummary();
    }

    loadTimeSlotsForSelectedDates() {
        if (this.selectedDates.length === 0) {
            this.updateTimeSlots([]);
            return;
        }
        
        $.ajax({
            url: bookingAjaxUrl,
            type: 'POST',
            data: {
                action: 'getTimeSlots',
                dates: this.selectedDates,
                id_booker: this.currentBooker
            },
            success: (response) => {
                if (response.success) {
                    this.updateTimeSlots(response.timeSlots);
                } else {
                    this.showError(response.error || 'Erreur lors du chargement des créneaux');
                }
            },
            error: () => this.showError('Erreur de connexion')
        });
    }

    updateCalendarSelection() {
        $('.calendar-day').removeClass('selected');
        this.selectedDates.forEach(date => {
            $(`.calendar-day[data-date="${date}"]`).addClass('selected');
        });
    }

    updateTimeSlotsSelection() {
        $('.time-slot').removeClass('selected');
        this.selectedTimeSlots.forEach(slot => {
            $(`.time-slot[data-date="${slot.date}"][data-hour-from="${slot.hour_from}"]`).addClass('selected');
        });
    }

    updateBookingSummary() {
        const summary = $('.booking-summary');
        
        if (this.selectedTimeSlots.length === 0) {
            summary.html('<p>Aucune réservation sélectionnée</p>');
            $('.booking-submit').prop('disabled', true);
            return;
        }
        
        let totalPrice = 0;
        let html = '<div class="summary-items">';
        
        this.selectedTimeSlots.forEach(slot => {
            const price = this.getSlotPrice(slot);
            totalPrice += price;
            
            html += `
                <div class="summary-item">
                    <span class="item-date">${this.formatDate(slot.date)}</span>
                    <span class="item-time">${this.formatTime(slot.hour_from)} - ${this.formatTime(slot.hour_to)}</span>
                    <span class="item-price">${price}€</span>
                </div>
            `;
        });
        
        html += `
            </div>
            <div class="summary-total">
                <strong>Total: ${totalPrice}€</strong>
            </div>
        `;
        
        summary.html(html);
        $('.booking-submit').prop('disabled', false);
    }

    submitBooking() {
        if (this.selectedTimeSlots.length === 0) {
            this.showError('Veuillez sélectionner au moins un créneau');
            return;
        }
        
        // Collecter les informations client
        const customerInfo = this.collectCustomerInfo();
        if (!this.validateCustomerInfo(customerInfo)) {
            return;
        }
        
        // Afficher la modal de confirmation
        this.showConfirmationModal(customerInfo);
    }

    collectCustomerInfo() {
        return {
            firstname: $('#booking_firstname').val(),
            lastname: $('#booking_lastname').val(),
            email: $('#booking_email').val(),
            phone: $('#booking_phone').val(),
            message: $('#booking_message').val()
        };
    }

    validateCustomerInfo(info) {
        if (!info.firstname || !info.lastname || !info.email) {
            this.showError('Veuillez remplir tous les champs obligatoires');
            return false;
        }
        
        if (!this.isValidEmail(info.email)) {
            this.showError('Veuillez saisir un email valide');
            return false;
        }
        
        return true;
    }

    showConfirmationModal(customerInfo) {
        const modal = $('#booking-confirmation-modal');
        
        // Remplir les détails de la modal
        $('.modal-customer-name').text(`${customerInfo.firstname} ${customerInfo.lastname}`);
        $('.modal-customer-email').text(customerInfo.email);
        $('.modal-booking-details').html(this.generateBookingDetailsHTML());
        
        modal.modal('show');
    }

    confirmBooking() {
        const customerInfo = this.collectCustomerInfo();
        
        $('.booking-confirm').prop('disabled', true).text('Envoi en cours...');
        
        $.ajax({
            url: bookingAjaxUrl,
            type: 'POST',
            data: {
                action: 'createBooking',
                id_booker: this.currentBooker,
                time_slots: this.selectedTimeSlots,
                customer: customerInfo
            },
            success: (response) => {
                if (response.success) {
                    this.showSuccess('Votre demande de réservation a été envoyée avec succès !');
                    $('#booking-confirmation-modal').modal('hide');
                    this.resetForm();
                    
                    // Redirection vers page de paiement si nécessaire
                    if (response.payment_url) {
                        setTimeout(() => {
                            window.location.href = response.payment_url;
                        }, 2000);
                    }
                } else {
                    this.showError(response.error || 'Erreur lors de la création de la réservation');
                }
            },
            error: () => {
                this.showError('Erreur de connexion');
            },
            complete: () => {
                $('.booking-confirm').prop('disabled', false).text('Confirmer la réservation');
            }
        });
    }

    cancelBooking() {
        $('#booking-confirmation-modal').modal('hide');
    }

    resetForm() {
        this.selectedDates = [];
        this.selectedTimeSlots = [];
        $('#booking-form')[0].reset();
        this.updateCalendarSelection();
        this.updateTimeSlotsSelection();
        this.updateBookingSummary();
        this.loadCalendar();
    }

    // Méthodes utilitaires
    previousMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
        this.loadCalendar();
    }

    nextMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
        this.loadCalendar();
    }

    selectBooker(bookerId) {
        this.currentBooker = bookerId;
        this.resetForm();
    }

    isToday(date) {
        const today = new Date();
        return date.toDateString() === today.toDateString();
    }

    isMultiSelectEnabled() {
        return $('#booking-form').data('multi-select') === true;
    }

    formatTime(hour) {
        return hour.toString().padStart(2, '0') + ':00';
    }

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    getSlotPrice(slot) {
        // À implémenter selon votre logique de prix
        return this.bookerData.price || 50;
    }

    generateBookingDetailsHTML() {
        let html = '<div class="booking-details">';
        
        this.selectedTimeSlots.forEach(slot => {
            html += `
                <div class="detail-item">
                    <i class="material-icons">event</i>
                    <span>${this.formatDate(slot.date)} de ${this.formatTime(slot.hour_from)} à ${this.formatTime(slot.hour_to)}</span>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showNotification(message, type = 'info') {
        const notification = $(`
            <div class="booking-notification ${type}">
                <span class="notification-message">${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.addClass('show');
        }, 100);
        
        notification.find('.notification-close').on('click', () => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        });
        
        if (type === 'success') {
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
    }
}

// Initialisation
$(document).ready(() => {
    if ($('.booking-interface').length) {
        window.bookingFrontend = new BookingFrontend();
    }
});