{extends file='page.tpl'}

{block name="page_title"}
    {l s='Paiement de votre réservation' d='Modules.Booking.Front'}
{/block}

{block name="page_content"}
<div class="payment-container">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 offset-lg-2">
                
                {* En-tête de confirmation *}
                <div class="payment-header">
                    <div class="text-center mb-4">
                        <i class="fa fa-check-circle fa-3x text-success"></i>
                        <h2 class="mt-3">{l s='Réservation confirmée !' d='Modules.Booking.Front'}</h2>
                        <p class="lead">{l s='Finalisez votre réservation en procédant au paiement' d='Modules.Booking.Front'}</p>
                    </div>
                </div>

                {* Détails de la réservation *}
                <div class="reservation-summary">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fa fa-calendar"></i>
                                {l s='Résumé de votre réservation' d='Modules.Booking.Front'}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>{l s='Informations de réservation' d='Modules.Booking.Front'}</h5>
                                    <dl class="row">
                                        <dt class="col-sm-5">{l s='Référence :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            <strong class="text-primary">#{$reservation->booking_reference}</strong>
                                        </dd>
                                        
                                        <dt class="col-sm-5">{l s='Élément :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">{$booker->name|escape:'html':'UTF-8'}</dd>
                                        
                                        <dt class="col-sm-5">{l s='Date :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            {$reservation->date_reserved|date_format:'%A %d %B %Y'}
                                        </dd>
                                        
                                        <dt class="col-sm-5">{l s='Heure :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            {$reservation->hour_from}h - {$reservation->hour_to}h
                                        </dd>
                                        
                                        {if $reservation->date_to && $reservation->date_to != $reservation->date_reserved}
                                        <dt class="col-sm-5">{l s='Date de fin :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            {$reservation->date_to|date_format:'%A %d %B %Y'}
                                        </dd>
                                        {/if}
                                    </dl>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>{l s='Informations client' d='Modules.Booking.Front'}</h5>
                                    <dl class="row">
                                        <dt class="col-sm-4">{l s='Nom :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-8">
                                            {$reservation->customer_firstname} {$reservation->customer_lastname}
                                        </dd>
                                        
                                        <dt class="col-sm-4">{l s='Email :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-8">{$reservation->customer_email}</dd>
                                        
                                        {if $reservation->customer_phone}
                                        <dt class="col-sm-4">{l s='Téléphone :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-8">{$reservation->customer_phone}</dd>
                                        {/if}
                                        
                                        {if $reservation->customer_message}
                                        <dt class="col-sm-4">{l s='Message :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-8">
                                            <small class="text-muted">{$reservation->customer_message|escape:'html':'UTF-8'}</small>
                                        </dd>
                                        {/if}
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {* Détails des prix *}
                <div class="pricing-details">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0">
                                <i class="fa fa-calculator"></i>
                                {l s='Détail des prix' d='Modules.Booking.Front'}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <table class="table table-borderless">
                                        <tbody>
                                            <tr>
                                                <td>{l s='Prix de la réservation' d='Modules.Booking.Front'}</td>
                                                <td class="text-right">
                                                    {$reservation->total_price|string_format:"%.2f"} €
                                                </td>
                                            </tr>
                                            
                                            {if $reservation->deposit_amount > 0}
                                            <tr class="table-warning">
                                                <td>
                                                    <strong>{l s='Caution à verser' d='Modules.Booking.Front'}</strong>
                                                    <small class="text-muted d-block">
                                                        {l s='Remboursée après retour en bon état' d='Modules.Booking.Front'}
                                                    </small>
                                                </td>
                                                <td class="text-right">
                                                    <strong>{$reservation->deposit_amount|string_format:"%.2f"} €</strong>
                                                </td>
                                            </tr>
                                            {/if}
                                        </tbody>
                                        <tfoot class="table-dark">
                                            <tr>
                                                <th>{l s='Total à payer aujourd\'hui' d='Modules.Booking.Front'}</th>
                                                <th class="text-right">
                                                    {assign var="total" value=($reservation->total_price + $reservation->deposit_amount)}
                                                    {$total|string_format:"%.2f"} €
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="alert alert-light border">
                                        <h6 class="alert-heading">
                                            <i class="fa fa-info-circle"></i>
                                            {l s='Information importante' d='Modules.Booking.Front'}
                                        </h6>
                                        <small>
                                            {l s='Le paiement sécurisé est traité par Stripe. Votre réservation sera confirmée immédiatement après le paiement.' d='Modules.Booking.Front'}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {* Boutons d'action *}
                <div class="payment-actions">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <h5 class="card-title">
                                    {l s='Procéder au paiement sécurisé' d='Modules.Booking.Front'}
                                </h5>
                                <p class="text-muted">
                                    {l s='Vous allez être redirigé vers notre plateforme de paiement sécurisée Stripe' d='Modules.Booking.Front'}
                                </p>
                            </div>
                            
                            <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                                <a href="{$payment_url}" 
                                   class="btn btn-primary btn-lg px-5 py-3"
                                   id="stripe-payment-btn">
                                    <i class="fa fa-credit-card mr-2"></i>
                                    {l s='Payer par carte bancaire' d='Modules.Booking.Front'}
                                    <small class="d-block mt-1">
                                        {assign var="total" value=($reservation->total_price + $reservation->deposit_amount)}
                                        {$total|string_format:"%.2f"} €
                                    </small>
                                </a>
                                
                                <div class="text-muted mx-3">
                                    {l s='ou' d='Modules.Booking.Front'}
                                </div>
                                
                                <a href="{$link->getPageLink('index')}" 
                                   class="btn btn-outline-secondary">
                                    <i class="fa fa-arrow-left mr-2"></i>
                                    {l s='Retour à l\'accueil' d='Modules.Booking.Front'}
                                </a>
                            </div>
                            
                            <div class="mt-4">
                                <div class="row justify-content-center">
                                    <div class="col-md-8">
                                        <div class="alert alert-info">
                                            <small>
                                                <i class="fa fa-shield-alt mr-1"></i>
                                                {l s='Paiement 100% sécurisé avec Stripe. Vos données bancaires ne transitent jamais par nos serveurs.' d='Modules.Booking.Front'}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {* Conditions et informations légales *}
                <div class="legal-info mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fa fa-file-contract"></i>
                                {l s='Conditions de réservation' d='Modules.Booking.Front'}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>{l s='Politique d\'annulation' d='Modules.Booking.Front'}</h6>
                                    <ul class="small text-muted">
                                        <li>{l s='Annulation gratuite jusqu\'à 24h avant la réservation' d='Modules.Booking.Front'}</li>
                                        <li>{l s='Annulation entre 24h et 2h : remboursement de 50%' d='Modules.Booking.Front'}</li>
                                        <li>{l s='Annulation moins de 2h avant : aucun remboursement' d='Modules.Booking.Front'}</li>
                                    </ul>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>{l s='Caution' d='Modules.Booking.Front'}</h6>
                                    <p class="small text-muted">
                                        {l s='La caution sera automatiquement remboursée sur votre carte dans les 7 jours suivant le retour, sauf dégradations constatées.' d='Modules.Booking.Front'}
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    {l s='En procédant au paiement, vous acceptez nos' d='Modules.Booking.Front'} 
                                    <a href="{$link->getCMSLink(3)}" target="_blank">
                                        {l s='conditions générales de vente' d='Modules.Booking.Front'}
                                    </a>
                                    {l s='et notre' d='Modules.Booking.Front'}
                                    <a href="{$link->getCMSLink(3)}" target="_blank">
                                        {l s='politique de confidentialité' d='Modules.Booking.Front'}
                                    </a>.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* JavaScript pour le paiement *}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentBtn = document.getElementById('stripe-payment-btn');
    
    if (paymentBtn) {
        paymentBtn.addEventListener('click', function(e) {
            // Optionnel : ajouter un loader ou une animation
            this.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>{l s="Redirection en cours..." d="Modules.Booking.Front"}';
            this.disabled = true;
        });
    }
});
</script>

{* CSS spécifique *}
<style>
.payment-container {
    background: #f8f9fa;
    min-height: 70vh;
    padding: 30px 0;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.btn-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border: none;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: linear-gradient(45deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,123,255,0.3);
}

.alert-info {
    background: linear-gradient(45deg, #d1ecf1, #bee5eb);
    border: 1px solid #bee5eb;
}

.table-dark th {
    font-size: 1.1em;
}

.payment-header i {
    color: #28a745;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@media (max-width: 768px) {
    .payment-container {
        padding: 15px 0;
    }
    
    .btn-lg {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .d-flex.flex-wrap {
        flex-direction: column;
    }
}
</style>
{/block}