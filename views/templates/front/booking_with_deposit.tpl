{*
 * Template front-end moderne pour la réservation avec caution Stripe
 * Version 2.1.4 - Interface responsive avec gestion de l'empreinte CB
 *}

{extends file='page.tpl'}

{block name='page_title'}
    <h1>Réservation - {$booker.name|escape:'htmlall':'UTF-8'}</h1>
{/block}

{block name='page_content'}
<div id="booking-app" class="booking-container">
    
    {* Messages d'erreur/succès *}
    {if $errors && count($errors)}
        <div class="alert alert-danger">
            <ul class="mb-0">
                {foreach $errors as $error}
                    <li>{$error|escape:'htmlall':'UTF-8'}</li>
                {/foreach}
            </ul>
        </div>
    {/if}
    
    {if $success_message}
        <div class="alert alert-success">
            {$success_message|escape:'htmlall':'UTF-8'}
        </div>
    {/if}

    {* Progression de la réservation *}
    <div class="booking-progress">
        <div class="progress-steps">
            <div class="step {if $current_step >= 1}active{/if} {if $current_step > 1}completed{/if}">
                <span class="step-number">1</span>
                <span class="step-title">Sélection</span>
            </div>
            <div class="step {if $current_step >= 2}active{/if} {if $current_step > 2}completed{/if}">
                <span class="step-number">2</span>
                <span class="step-title">Informations</span>
            </div>
            <div class="step {if $current_step >= 3}active{/if} {if $current_step > 3}completed{/if}">
                <span class="step-number">3</span>
                <span class="step-title">Caution</span>
            </div>
            <div class="step {if $current_step >= 4}active{/if}">
                <span class="step-number">4</span>
                <span class="step-title">Confirmation</span>
            </div>
        </div>
    </div>

    <div class="row">
        {* Colonne principale - Formulaire *}
        <div class="col-lg-8">
            
            {* Étape 1 - Sélection de créneau *}
            {if $current_step == 1}
                <div class="booking-step step-selection">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fa fa-calendar"></i>
                                Choisissez votre créneau
                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="booking-form-step1" method="post" action="{$form_action}">
                                <input type="hidden" name="step" value="1">
                                <input type="hidden" name="id_booker" value="{$booker.id_booker}">
                                
                                {* Calendrier de disponibilités *}
                                <div id="availability-calendar"></div>
                                
                                {* Sélection des heures *}
                                <div class="time-selection mt-4" style="display: none;">
                                    <h4>Sélectionnez l'horaire</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="hour_from">Heure de début</label>
                                            <select id="hour_from" name="hour_from" class="form-control" required>
                                                <option value="">-- Choisir --</option>
                                                {for $i=8 to 20}
                                                    <option value="{$i}">{$i}:00</option>
                                                {/for}
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="hour_to">Heure de fin</label>
                                            <select id="hour_to" name="hour_to" class="form-control" required>
                                                <option value="">-- Choisir --</option>
                                                {for $i=9 to 21}
                                                    <option value="{$i}">{$i}:00</option>
                                                {/for}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <input type="hidden" id="selected_date" name="selected_date">
                                
                                <div class="text-right mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg" disabled id="continue-step1">
                                        Continuer <i class="fa fa-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            {/if}
            
            {* Étape 2 - Informations client *}
            {if $current_step == 2}
                <div class="booking-step step-info">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fa fa-user"></i>
                                Vos informations
                            </h3>
                        </div>
                        <div class="card-body">
                            <form id="booking-form-step2" method="post" action="{$form_action}">
                                <input type="hidden" name="step" value="2">
                                <input type="hidden" name="id_booker" value="{$booker.id_booker}">
                                <input type="hidden" name="selected_date" value="{$booking_data.selected_date}">
                                <input type="hidden" name="hour_from" value="{$booking_data.hour_from}">
                                <input type="hidden" name="hour_to" value="{$booking_data.hour_to}">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_firstname" class="required">Prénom</label>
                                            <input type="text" id="customer_firstname" name="customer_firstname" 
                                                   class="form-control" required
                                                   value="{if $customer.firstname}{$customer.firstname}{/if}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_lastname" class="required">Nom</label>
                                            <input type="text" id="customer_lastname" name="customer_lastname" 
                                                   class="form-control" required
                                                   value="{if $customer.lastname}{$customer.lastname}{/if}">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_email" class="required">Email</label>
                                            <input type="email" id="customer_email" name="customer_email" 
                                                   class="form-control" required
                                                   value="{if $customer.email}{$customer.email}{/if}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="customer_phone">Téléphone</label>
                                            <input type="tel" id="customer_phone" name="customer_phone" 
                                                   class="form-control"
                                                   value="{if $customer.phone}{$customer.phone}{/if}">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Notes / Demandes particulières</label>
                                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                                              placeholder="Indiquez vos demandes particulières..."></textarea>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" id="accept_terms" name="accept_terms" 
                                           class="form-check-input" required value="1">
                                    <label for="accept_terms" class="form-check-label">
                                        J'accepte les <a href="#" data-toggle="modal" data-target="#terms-modal">conditions générales</a> *
                                    </label>
                                </div>
                                
                                <div class="text-right mt-4">
                                    <button type="button" class="btn btn-secondary me-2" onclick="goToStep(1)">
                                        <i class="fa fa-arrow-left"></i> Retour
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        Continuer <i class="fa fa-arrow-right"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            {/if}
            
            {* Étape 3 - Gestion de la caution *}
            {if $current_step == 3}
                <div class="booking-step step-deposit">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fa fa-credit-card"></i>
                                Caution de garantie
                            </h3>
                        </div>
                        <div class="card-body">
                            
                            {* Explication de la caution *}
                            <div class="alert alert-info mb-4">
                                <h5><i class="fa fa-info-circle"></i> À propos de la caution</h5>
                                <p class="mb-2">
                                    Une caution de <strong>{$deposit_amount_formatted}</strong> sera préautorisée sur votre carte bancaire 
                                    pour garantir la réservation.
                                </p>
                                <ul class="mb-0">
                                    <li>Aucun débit n'aura lieu immédiatement</li>
                                    <li>La caution sera libérée automatiquement après votre réservation</li>
                                    <li>Elle ne sera débitée qu'en cas de dommages ou non-respect des conditions</li>
                                </ul>
                            </div>
                            
                            <form id="booking-form-step3" method="post" action="{$form_action}">
                                <input type="hidden" name="step" value="3">
                                <input type="hidden" name="booking_data" value="{$booking_data_json}">
                                
                                {* Formulaire de carte bancaire Stripe *}
                                <div class="card-input-container">
                                    <h5>Informations de carte bancaire</h5>
                                    
                                    <div class="form-group">
                                        <label for="cardholder_name">Nom du porteur</label>
                                        <input type="text" id="cardholder_name" name="cardholder_name" 
                                               class="form-control" required
                                               value="{$booking_data.customer_firstname} {$booking_data.customer_lastname}">
                                    </div>
                                    
                                    {* Élément Stripe Elements pour la carte *}
                                    <div class="form-group">
                                        <label for="card-element">Numéro de carte</label>
                                        <div id="card-element" class="form-control stripe-element">
                                            <!-- Stripe Elements sera injecté ici -->
                                        </div>
                                        <div id="card-errors" role="alert" class="text-danger mt-2"></div>
                                    </div>
                                    
                                    {* Informations de sécurité *}
                                    <div class="security-info">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <small class="text-muted">
                                                    <i class="fa fa-lock"></i>
                                                    Vos données bancaires sont sécurisées par Stripe (certifié PCI DSS)
                                                </small>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <img src="{$module_dir}views/img/stripe-badges.png" alt="Sécurisé par Stripe" class="img-fluid">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-right mt-4">
                                    <button type="button" class="btn btn-secondary me-2" onclick="goToStep(2)">
                                        <i class="fa fa-arrow-left"></i> Retour
                                    </button>
                                    <button type="submit" id="submit-deposit" class="btn btn-primary btn-lg" disabled>
                                        <span id="submit-text">Autoriser la caution</span>
                                        <span id="submit-spinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            {/if}
            
            {* Étape 4 - Confirmation *}
            {if $current_step == 4}
                <div class="booking-step step-confirmation">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h3 class="card-title mb-0">
                                <i class="fa fa-check-circle"></i>
                                Réservation confirmée !
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success">
                                <h4>Félicitations !</h4>
                                <p class="mb-0">
                                    Votre réservation <strong>#{$reservation.booking_reference}</strong> a été créée avec succès.
                                    Un email de confirmation vous a été envoyé.
                                </p>
                            </div>
                            
                            <div class="confirmation-details">
                                <h5>Détails de votre réservation</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Date :</strong> {$reservation.date_reserved|date_format:"%d/%m/%Y"}<br>
                                        <strong>Horaire :</strong> {$reservation.hour_from}h - {$reservation.hour_to}h<br>
                                        <strong>Élément :</strong> {$booker.name}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Prix :</strong> {$reservation.total_price|string_format:"%.2f"}€<br>
                                        <strong>Caution :</strong> {$reservation.deposit_paid|string_format:"%.2f"}€ (préautorisée)<br>
                                        <strong>Statut :</strong> <span class="badge badge-warning">En attente de validation</span>
                                    </div>
                                </div>
                            </div>
                            
                            {if $reservation.status == $smarty.const.BookerAuthReserved::STATUS_PENDING}
                                <div class="alert alert-info mt-3">
                                    <i class="fa fa-clock"></i>
                                    Votre réservation est en attente de validation par notre équipe. 
                                    Vous recevrez un email dès qu'elle sera confirmée.
                                </div>
                            {/if}
                            
                            <div class="action-buttons mt-4">
                                <a href="{$urls.base_url}" class="btn btn-secondary">
                                    <i class="fa fa-home"></i> Retour à l'accueil
                                </a>
                                <button type="button" class="btn btn-primary" onclick="window.print()">
                                    <i class="fa fa-print"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
        
        {* Colonne sidebar - Récapitulatif *}
        <div class="col-lg-4">
            <div class="booking-summary card sticky-top">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        <i class="fa fa-clipboard-list"></i>
                        Récapitulatif
                    </h4>
                </div>
                <div class="card-body">
                    
                    {* Informations sur l'élément *}
                    <div class="booker-info mb-3">
                        {if $booker.image}
                            <img src="{$booker.image}" alt="{$booker.name}" class="img-fluid rounded mb-2">
                        {/if}
                        <h5>{$booker.name|escape:'htmlall':'UTF-8'}</h5>
                        {if $booker.description}
                            <p class="text-muted small">{$booker.description|truncate:100}</p>
                        {/if}
                    </div>
                    
                    {* Détails de la réservation *}
                    {if $booking_data}
                        <div class="booking-details">
                            <hr>
                            <div class="detail-row">
                                <span class="label">Date :</span>
                                <span class="value">{$booking_data.selected_date|date_format:"%d/%m/%Y"}</span>
                            </div>
                            
                            {if $booking_data.hour_from && $booking_data.hour_to}
                                <div class="detail-row">
                                    <span class="label">Horaire :</span>
                                    <span class="value">{$booking_data.hour_from}h - {$booking_data.hour_to}h</span>
                                </div>
                                
                                <div class="detail-row">
                                    <span class="label">Durée :</span>
                                    <span class="value">{$booking_data.hour_to - $booking_data.hour_from}h</span>
                                </div>
                            {/if}
                            
                            {if $booking_data.customer_firstname}
                                <div class="detail-row">
                                    <span class="label">Client :</span>
                                    <span class="value">{$booking_data.customer_firstname} {$booking_data.customer_lastname}</span>
                                </div>
                            {/if}
                        </div>
                    {/if}
                    
                    {* Calcul des prix *}
                    {if $price_calculation}
                        <div class="price-breakdown">
                            <hr>
                            <div class="detail-row">
                                <span class="label">Prix de base :</span>
                                <span class="value">{$price_calculation.base_price|string_format:"%.2f"}€</span>
                            </div>
                            
                            {if $price_calculation.extra_fees > 0}
                                <div class="detail-row">
                                    <span class="label">Frais supplémentaires :</span>
                                    <span class="value">{$price_calculation.extra_fees|string_format:"%.2f"}€</span>
                                </div>
                            {/if}
                            
                            <div class="detail-row total">
                                <span class="label"><strong>Total :</strong></span>
                                <span class="value"><strong>{$price_calculation.total_price|string_format:"%.2f"}€</strong></span>
                            </div>
                            
                            {if $current_step >= 3}
                                <div class="detail-row deposit">
                                    <span class="label">Caution (préautorisée) :</span>
                                    <span class="value text-warning">{$deposit_amount_formatted}</span>
                                </div>
                            {/if}
                        </div>
                    {/if}
                    
                    {* Informations importantes *}
                    <div class="important-info mt-3">
                        <hr>
                        <h6><i class="fa fa-exclamation-triangle text-warning"></i> Important</h6>
                        <ul class="small text-muted">
                            <li>La réservation doit être validée sous 48h</li>
                            <li>Annulation gratuite jusqu'à 24h avant</li>
                            <li>Présence d'une pièce d'identité requise</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* Modal pour les conditions générales *}
<div class="modal fade" id="terms-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conditions générales de réservation</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>En effectuant cette réservation, vous acceptez les conditions suivantes :</p>
                <ul>
                    <li>Le paiement intégral est dû lors de la confirmation de la réservation</li>
                    <li>Une caution est préautorisée pour garantir la réservation</li>
                    <li>Toute dégradation sera facturée sur la caution</li>
                    <li>L'annulation doit être effectuée au moins 24h à l'avance</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">J'accepte</button>
            </div>
        </div>
    </div>
</div>

{/block}

{block name='page_footer'}
    {* Scripts spécifiques *}
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Configuration Stripe
        const stripe = Stripe('{$stripe_public_key}');
        const elements = stripe.elements();
        
        // Éléments Stripe
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });
        
        if (document.getElementById('card-element')) {
            cardElement.mount('#card-element');
            
            // Gestion des erreurs
            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                const submitButton = document.getElementById('submit-deposit');
                
                if (event.error) {
                    displayError.textContent = event.error.message;
                    submitButton.disabled = true;
                } else {
                    displayError.textContent = '';
                    submitButton.disabled = !event.complete;
                }
            });
            
            // Soumission du formulaire avec caution
            document.getElementById('booking-form-step3').addEventListener('submit', async function(event) {
                event.preventDefault();
                
                const submitButton = document.getElementById('submit-deposit');
                const submitText = document.getElementById('submit-text');
                const submitSpinner = document.getElementById('submit-spinner');
                
                // Désactiver le bouton et afficher le spinner
                submitButton.disabled = true;
                submitText.textContent = 'Traitement en cours...';
                submitSpinner.style.display = 'inline-block';
                
                try {
                    // Créer le Setup Intent pour la caution
                    const result = await stripe.confirmCardSetup('{$setup_intent_client_secret}', {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name: document.getElementById('cardholder_name').value,
                                email: '{$booking_data.customer_email}',
                            },
                        },
                    });
                    
                    if (result.error) {
                        // Afficher l'erreur
                        document.getElementById('card-errors').textContent = result.error.message;
                        
                        // Réactiver le bouton
                        submitButton.disabled = false;
                        submitText.textContent = 'Autoriser la caution';
                        submitSpinner.style.display = 'none';
                    } else {
                        // Succès - rediriger vers l'étape suivante
                        const hiddenForm = document.createElement('form');
                        hiddenForm.method = 'POST';
                        hiddenForm.action = '{$form_action}';
                        
                        const inputs = [
                            { name: 'step', value: '4' },
                            { name: 'setup_intent_id', value: result.setupIntent.id },
                            { name: 'payment_method_id', value: result.setupIntent.payment_method },
                            { name: 'booking_data', value: '{$booking_data_json}' }
                        ];
                        
                        inputs.forEach(input => {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = input.name;
                            hiddenInput.value = input.value;
                            hiddenForm.appendChild(hiddenInput);
                        });
                        
                        document.body.appendChild(hiddenForm);
                        hiddenForm.submit();
                    }
                } catch (error) {
                    console.error('Erreur Stripe:', error);
                    document.getElementById('card-errors').textContent = 'Une erreur inattendue s\'est produite.';
                    
                    // Réactiver le bouton
                    submitButton.disabled = false;
                    submitText.textContent = 'Autoriser la caution';
                    submitSpinner.style.display = 'none';
                }
            });
        }
        
        // Fonction pour changer d'étape
        function goToStep(step) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{$form_action}';
            
            const stepInput = document.createElement('input');
            stepInput.type = 'hidden';
            stepInput.name = 'step';
            stepInput.value = step;
            form.appendChild(stepInput);
            
            const dataInput = document.createElement('input');
            dataInput.type = 'hidden';
            dataInput.name = 'booking_data';
            dataInput.value = '{$booking_data_json}';
            form.appendChild(dataInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Validation des heures
        document.addEventListener('DOMContentLoaded', function() {
            const hourFrom = document.getElementById('hour_from');
            const hourTo = document.getElementById('hour_to');
            
            if (hourFrom && hourTo) {
                function validateHours() {
                    const from = parseInt(hourFrom.value);
                    const to = parseInt(hourTo.value);
                    
                    if (from && to && to <= from) {
                        hourTo.setCustomValidity('L\'heure de fin doit être après l\'heure de début');
                    } else {
                        hourTo.setCustomValidity('');
                    }
                }
                
                hourFrom.addEventListener('change', validateHours);
                hourTo.addEventListener('change', validateHours);
            }
        });
    </script>
{/block}
