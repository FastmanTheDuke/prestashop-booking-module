{extends file='page.tpl'}

{block name="page_title"}
    {if $success}
        {l s='Paiement réussi !' d='Modules.Booking.Front'}
    {elseif $cancelled}
        {l s='Paiement annulé' d='Modules.Booking.Front'}
    {else}
        {l s='Problème de paiement' d='Modules.Booking.Front'}
    {/if}
{/block}

{block name="page_content"}
<div class="payment-result-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                {* Résultat SUCCESS *}
                {if $success}
                <div class="result-success">
                    <div class="text-center mb-4">
                        <div class="success-animation">
                            <i class="fa fa-check-circle fa-5x text-success"></i>
                        </div>
                        <h1 class="mt-4 text-success">{l s='Paiement réussi !' d='Modules.Booking.Front'}</h1>
                        <p class="lead text-muted">
                            {l s='Votre réservation a été confirmée et payée avec succès' d='Modules.Booking.Front'}
                        </p>
                    </div>
                    
                    {* Détails de la réservation confirmée *}
                    <div class="card border-success mb-4">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">
                                <i class="fa fa-calendar-check"></i>
                                {l s='Réservation confirmée' d='Modules.Booking.Front'}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-6">{l s='Référence :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-6">
                                            <strong class="text-primary">#{$reservation->booking_reference}</strong>
                                        </dd>
                                        
                                        <dt class="col-sm-6">{l s='Élément :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-6">{$booker->name|escape:'html':'UTF-8'}</dd>
                                        
                                        <dt class="col-sm-6">{l s='Date :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-6">
                                            {$reservation->date_reserved|date_format:'%A %d %B %Y'}
                                        </dd>
                                        
                                        <dt class="col-sm-6">{l s='Heure :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-6">
                                            {$reservation->hour_from}h - {$reservation->hour_to}h
                                        </dd>
                                    </dl>
                                </div>
                                
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-5">{l s='Client :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            {$reservation->customer_firstname} {$reservation->customer_lastname}
                                        </dd>
                                        
                                        <dt class="col-sm-5">{l s='Email :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">{$reservation->customer_email}</dd>
                                        
                                        <dt class="col-sm-5">{l s='Montant payé :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            <strong class="text-success">
                                                {assign var="total" value=($reservation->total_price + $reservation->deposit_amount)}
                                                {$total|string_format:"%.2f"} €
                                            </strong>
                                        </dd>
                                        
                                        {if $payment_confirmed}
                                        <dt class="col-sm-5">{l s='Statut :' d='Modules.Booking.Front'}</dt>
                                        <dd class="col-sm-7">
                                            <span class="badge badge-success">
                                                {l s='Paiement confirmé' d='Modules.Booking.Front'}
                                            </span>
                                        </dd>
                                        {/if}
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {* Prochaines étapes *}
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fa fa-info-circle"></i>
                                {l s='Prochaines étapes' d='Modules.Booking.Front'}
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item completed">
                                    <div class="timeline-marker">
                                        <i class="fa fa-check"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>{l s='Paiement effectué' d='Modules.Booking.Front'}</h6>
                                        <p class="text-muted small">
                                            {l s='Votre paiement a été traité avec succès' d='Modules.Booking.Front'}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item completed">
                                    <div class="timeline-marker">
                                        <i class="fa fa-envelope"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>{l s='Email de confirmation envoyé' d='Modules.Booking.Front'}</h6>
                                        <p class="text-muted small">
                                            {l s='Vérifiez votre boîte mail (et les spams)' d='Modules.Booking.Front'}
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item pending">
                                    <div class="timeline-marker">
                                        <i class="fa fa-calendar"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>{l s='Jour de la réservation' d='Modules.Booking.Front'}</h6>
                                        <p class="text-muted small">
                                            {l s='Présentez-vous avec votre référence de réservation' d='Modules.Booking.Front'}
                                        </p>
                                    </div>
                                </div>
                                
                                {if $reservation->deposit_amount > 0}
                                <div class="timeline-item pending">
                                    <div class="timeline-marker">
                                        <i class="fa fa-credit-card"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6>{l s='Remboursement de la caution' d='Modules.Booking.Front'}</h6>
                                        <p class="text-muted small">
                                            {l s='Dans les 7 jours après retour en bon état' d='Modules.Booking.Front'}
                                        </p>
                                    </div>
                                </div>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
                {/if}
                
                {* Résultat CANCELLED *}
                {if $cancelled}
                <div class="result-cancelled">
                    <div class="text-center mb-4">
                        <i class="fa fa-times-circle fa-5x text-warning"></i>
                        <h1 class="mt-4 text-warning">{l s='Paiement annulé' d='Modules.Booking.Front'}</h1>
                        <p class="lead text-muted">
                            {l s='Vous avez annulé le processus de paiement' d='Modules.Booking.Front'}
                        </p>
                    </div>
                    
                    <div class="card border-warning mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0">
                                <i class="fa fa-exclamation-triangle"></i>
                                {l s='Réservation en attente' d='Modules.Booking.Front'}
                            </h4>
                        </div>
                        <div class="card-body">
                            <p>{l s='Votre réservation n\'a pas été confirmée car le paiement a été annulé.' d='Modules.Booking.Front'}</p>
                            <p>{l s='Référence :' d='Modules.Booking.Front'} <strong>#{$reservation->booking_reference}</strong></p>
                            
                            <div class="alert alert-info">
                                <i class="fa fa-lightbulb"></i>
                                {l s='Vous pouvez reprendre le processus de paiement à tout moment en cliquant sur le bouton ci-dessous.' d='Modules.Booking.Front'}
                            </div>
                        </div>
                    </div>
                </div>
                {/if}
                
                {* Résultat ERROR (par défaut) *}
                {if !$success && !$cancelled}
                <div class="result-error">
                    <div class="text-center mb-4">
                        <i class="fa fa-exclamation-circle fa-5x text-danger"></i>
                        <h1 class="mt-4 text-danger">{l s='Problème de paiement' d='Modules.Booking.Front'}</h1>
                        <p class="lead text-muted">
                            {l s='Une erreur est survenue lors du traitement de votre paiement' d='Modules.Booking.Front'}
                        </p>
                    </div>
                    
                    <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0">
                                <i class="fa fa-times"></i>
                                {l s='Paiement non traité' d='Modules.Booking.Front'}
                            </h4>
                        </div>
                        <div class="card-body">
                            <p>{l s='Votre réservation n\'a pas pu être confirmée.' d='Modules.Booking.Front'}</p>
                            <p>{l s='Référence :' d='Modules.Booking.Front'} <strong>#{$reservation->booking_reference}</strong></p>
                            
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i>
                                {l s='Aucun montant n\'a été débité de votre compte. Vous pouvez réessayer le paiement.' d='Modules.Booking.Front'}
                            </div>
                        </div>
                    </div>
                </div>
                {/if}
                
                {* Actions disponibles *}
                <div class="action-buttons">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title">{l s='Que souhaitez-vous faire ?' d='Modules.Booking.Front'}</h5>
                            
                            <div class="btn-group-vertical btn-group-lg d-block d-md-inline-flex" role="group">
                                {if $success}
                                    {if $account_url}
                                    <a href="{$account_url}" class="btn btn-primary mb-2">
                                        <i class="fa fa-user mr-2"></i>
                                        {l s='Voir mon compte' d='Modules.Booking.Front'}
                                    </a>
                                    {/if}
                                    
                                    <a href="{$home_url}" class="btn btn-outline-primary mb-2">
                                        <i class="fa fa-home mr-2"></i>
                                        {l s='Retour à l\'accueil' d='Modules.Booking.Front'}
                                    </a>
                                    
                                    <button onclick="window.print()" class="btn btn-outline-secondary mb-2">
                                        <i class="fa fa-print mr-2"></i>
                                        {l s='Imprimer cette page' d='Modules.Booking.Front'}
                                    </button>
                                    
                                {elseif $cancelled && $retry_url}
                                    <a href="{$retry_url}" class="btn btn-primary btn-lg mb-2">
                                        <i class="fa fa-credit-card mr-2"></i>
                                        {l s='Reprendre le paiement' d='Modules.Booking.Front'}
                                    </a>
                                    
                                    <a href="{$home_url}" class="btn btn-outline-secondary mb-2">
                                        <i class="fa fa-home mr-2"></i>
                                        {l s='Retour à l\'accueil' d='Modules.Booking.Front'}
                                    </a>
                                    
                                {else}
                                    {if $retry_url}
                                    <a href="{$retry_url}" class="btn btn-warning btn-lg mb-2">
                                        <i class="fa fa-redo mr-2"></i>
                                        {l s='Réessayer le paiement' d='Modules.Booking.Front'}
                                    </a>
                                    {/if}
                                    
                                    <a href="{$home_url}" class="btn btn-outline-secondary mb-2">
                                        <i class="fa fa-home mr-2"></i>
                                        {l s='Retour à l\'accueil' d='Modules.Booking.Front'}
                                    </a>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
                
                {* Informations de contact *}
                <div class="contact-info mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fa fa-question-circle"></i>
                                {l s='Besoin d\'aide ?' d='Modules.Booking.Front'}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>{l s='Service client' d='Modules.Booking.Front'}</h6>
                                    <p class="small text-muted">
                                        {if Configuration::get('BOOKING_EMERGENCY_PHONE')}
                                            <i class="fa fa-phone"></i> {Configuration::get('BOOKING_EMERGENCY_PHONE')}<br>
                                        {/if}
                                        <i class="fa fa-envelope"></i> {Configuration::get('PS_SHOP_EMAIL')}<br>
                                        <i class="fa fa-clock"></i> {l s='Du lundi au vendredi, 9h-18h' d='Modules.Booking.Front'}
                                    </p>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>{l s='Informations importantes' d='Modules.Booking.Front'}</h6>
                                    <p class="small text-muted">
                                        {l s='Conservez votre référence de réservation précieusement. Elle vous sera demandée lors de votre venue.' d='Modules.Booking.Front'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{* CSS spécifique *}
<style>
.payment-result-container {
    background: #f8f9fa;
    min-height: 70vh;
    padding: 30px 0;
}

.success-animation i {
    animation: successPulse 2s infinite;
}

@keyframes successPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -37px;
    top: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: white;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
}

.timeline-item.pending .timeline-marker {
    background: #ffc107;
    color: #212529;
}

.timeline-content h6 {
    margin: 0 0 5px 0;
    color: #495057;
}

.timeline-content p {
    margin: 0;
}

.card {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.btn-group-vertical .btn {
    margin-bottom: 10px;
}

.result-success h1 {
    font-weight: 300;
}

.result-cancelled h1,
.result-error h1 {
    font-weight: 300;
}

@media (max-width: 768px) {
    .payment-result-container {
        padding: 15px 0;
    }
    
    .btn-group-vertical .btn {
        width: 100%;
    }
    
    .timeline {
        padding-left: 25px;
    }
    
    .timeline-marker {
        left: -32px;
        width: 25px;
        height: 25px;
        font-size: 10px;
    }
}

@media print {
    .action-buttons,
    .contact-info {
        display: none;
    }
    
    .payment-result-container {
        background: white;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
}
</style>
{/block}