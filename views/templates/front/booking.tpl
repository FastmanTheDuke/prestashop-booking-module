{extends file='page.tpl'}

{block name="page_title"}
    {l s='Réservation en ligne' d='Modules.Booking.Front'}
{/block}

{block name="page_content"}
<div class="booking-interface">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h1>{l s='Réservez votre créneau' d='Modules.Booking.Front'}</h1>
                <p class="lead">{l s='Sélectionnez vos dates et créneaux horaires préférés' d='Modules.Booking.Front'}</p>
            </div>
        </div>
        
        {* Sélection du booker *}
        <div class="booker-selection">
            <h3>{l s='Que souhaitez-vous réserver ?' d='Modules.Booking.Front'}</h3>
            {if $bookers && count($bookers) > 0}
                <select class="booker-selector form-control" id="booker_selector">
                    <option value="">{l s='Sélectionnez un élément...' d='Modules.Booking.Front'}</option>
                    {foreach $bookers as $booker}
                        <option value="{$booker.id_booker}" 
                                {if $selected_booker == $booker.id_booker}selected{/if}
                                data-description="{$booker.description|escape:'html':'UTF-8'}"
                                data-price="{$booker.price|default:50}">
                            {$booker.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
                
                {* Informations sur le booker sélectionné *}
                {foreach $bookers as $booker}
                    <div class="booker-info" id="booker_info_{$booker.id_booker}" style="display: none;">
                        <h4>{$booker.name|escape:'html':'UTF-8'}</h4>
                        {if $booker.description}
                            <div class="booker-description">
                                {$booker.description nofilter}
                            </div>
                        {/if}
                        <div class="booker-price">
                            <strong>{l s='Tarif :' d='Modules.Booking.Front'} {$booker.price|default:50}€ {l s='par créneau' d='Modules.Booking.Front'}</strong>
                        </div>
                    </div>
                {/foreach}
            {else}
                <div class="alert alert-warning">
                    {l s='Aucun élément disponible à la réservation pour le moment.' d='Modules.Booking.Front'}
                </div>
            {/if}
        </div>
        
        <div class="booking-container" id="booking_container" style="display: none;">
            <div class="booking-main">
                {* Section calendrier *}
                <div class="booking-calendar-section">
                    <div class="booking-calendar">
                        {* Le calendrier sera généré par JavaScript *}
                        <div class="calendar-loading">
                            <i class="fa fa-spinner fa-spin"></i>
                            {l s='Chargement du calendrier...' d='Modules.Booking.Front'}
                        </div>
                    </div>
                </div>
                
                {* Section créneaux horaires *}
                <div class="time-slots-section">
                    <h3>{l s='Créneaux horaires disponibles' d='Modules.Booking.Front'}</h3>
                    <div class="time-slots-container">
                        <p class="no-slots">{l s='Sélectionnez une date pour voir les créneaux disponibles' d='Modules.Booking.Front'}</p>
                    </div>
                </div>
            </div>
            
            <div class="booking-sidebar">
                {* Formulaire client *}
                <div class="customer-form">
                    <h3>{l s='Vos informations' d='Modules.Booking.Front'}</h3>
                    <form id="booking-form" data-multi-select="true">
                        <div class="form-group">
                            <label for="booking_firstname" class="required">
                                {l s='Prénom' d='Modules.Booking.Front'}
                            </label>
                            <input type="text" 
                                   id="booking_firstname" 
                                   name="firstname" 
                                   class="form-control" 
                                   value="{if $customer_info}{$customer_info.firstname|escape:'html':'UTF-8'}{/if}"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking_lastname" class="required">
                                {l s='Nom' d='Modules.Booking.Front'}
                            </label>
                            <input type="text" 
                                   id="booking_lastname" 
                                   name="lastname" 
                                   class="form-control" 
                                   value="{if $customer_info}{$customer_info.lastname|escape:'html':'UTF-8'}{/if}"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking_email" class="required">
                                {l s='Email' d='Modules.Booking.Front'}
                            </label>
                            <input type="email" 
                                   id="booking_email" 
                                   name="email" 
                                   class="form-control" 
                                   value="{if $customer_info}{$customer_info.email|escape:'html':'UTF-8'}{/if}"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="booking_phone">
                                {l s='Téléphone' d='Modules.Booking.Front'}
                            </label>
                            <input type="tel" 
                                   id="booking_phone" 
                                   name="phone" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="booking_message">
                                {l s='Message (optionnel)' d='Modules.Booking.Front'}
                            </label>
                            <textarea id="booking_message" 
                                      name="message" 
                                      class="form-control" 
                                      rows="3"
                                      placeholder="{l s='Informations complémentaires...' d='Modules.Booking.Front'}"></textarea>
                        </div>
                    </form>
                </div>
                
                {* Résumé de réservation *}
                <div class="booking-summary">
                    <h3>{l s='Récapitulatif' d='Modules.Booking.Front'}</h3>
                    <p>{l s='Aucune réservation sélectionnée' d='Modules.Booking.Front'}</p>
                    
                    <button type="button" class="booking-submit btn btn-primary" disabled>
                        <i class="fa fa-calendar-check"></i>
                        {l s='Demander la réservation' d='Modules.Booking.Front'}
                    </button>
                    
                    <div class="booking-info mt-3">
                        <p class="small text-muted">
                            <i class="fa fa-info-circle"></i>
                            {l s='Votre demande sera examinée sous 24h. Un email de confirmation vous sera envoyé.' d='Modules.Booking.Front'}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* Modal de confirmation *}
<div class="modal fade" id="booking-confirmation-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-check-circle"></i>
                    {l s='Confirmer votre réservation' d='Modules.Booking.Front'}
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>{l s='Vos informations' d='Modules.Booking.Front'}</h6>
                        <p>
                            <strong class="modal-customer-name"></strong><br>
                            <span class="modal-customer-email"></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>{l s='Détails de la réservation' d='Modules.Booking.Front'}</h6>
                        <div class="modal-booking-details"></div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    {l s='Votre demande de réservation sera envoyée à notre équipe pour validation. Vous recevrez un email de confirmation dans les plus brefs délais.' d='Modules.Booking.Front'}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="booking-cancel btn btn-secondary">
                    {l s='Annuler' d='Modules.Booking.Front'}
                </button>
                <button type="button" class="booking-confirm btn btn-primary">
                    <i class="fa fa-paper-plane"></i>
                    {l s='Confirmer la réservation' d='Modules.Booking.Front'}
                </button>
            </div>
        </div>
    </div>
</div>

{* Légende du calendrier *}
<div class="calendar-legend mt-4">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <h5>{l s='Légende' d='Modules.Booking.Front'}</h5>
                <div class="legend-items">
                    <span class="legend-item">
                        <span class="legend-color available"></span>
                        {l s='Disponible' d='Modules.Booking.Front'}
                    </span>
                    <span class="legend-item">
                        <span class="legend-color selected"></span>
                        {l s='Sélectionné' d='Modules.Booking.Front'}
                    </span>
                    <span class="legend-item">
                        <span class="legend-color has-reservations"></span>
                        {l s='Partiellement réservé' d='Modules.Booking.Front'}
                    </span>
                    <span class="legend-item">
                        <span class="legend-color unavailable"></span>
                        {l s='Non disponible' d='Modules.Booking.Front'}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.calendar-legend {
    border-top: 1px solid #dee2e6;
    padding-top: 20px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    margin-right: 8px;
    border: 1px solid #dee2e6;
}

.legend-color.available {
    background: rgba(0, 118, 128, 0.1);
    border-color: var(--booking-primary);
}

.legend-color.selected {
    background: var(--booking-primary);
}

.legend-color.has-reservations {
    background: #fff3cd;
    border-color: #ffc107;
    position: relative;
}

.legend-color.has-reservations::after {
    content: '';
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 6px;
    height: 6px;
    background: #ffc107;
    border-radius: 50%;
}

.legend-color.unavailable {
    background: #f5f5f5;
    border-color: #ccc;
}

@media (max-width: 768px) {
    .legend-items {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
$(document).ready(function() {
    // Gestion de la sélection du booker
    $('#booker_selector').on('change', function() {
        const selectedBooker = $(this).val();
        
        // Masquer toutes les infos booker
        $('.booker-info').hide();
        
        if (selectedBooker) {
            // Afficher les infos du booker sélectionné
            $('#booker_info_' + selectedBooker).show();
            
            // Afficher le container de réservation
            $('#booking_container').show();
            
            // Initialiser le module de réservation
            if (window.bookingFrontend) {
                window.bookingFrontend.selectBooker(selectedBooker);
            }
        } else {
            // Masquer le container de réservation
            $('#booking_container').hide();
        }
    });
    
    // Déclencher le changement si un booker est pré-sélectionné
    if ($('#booker_selector').val()) {
        $('#booker_selector').trigger('change');
    }
});
</script>
{/block}