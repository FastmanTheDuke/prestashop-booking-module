{*
 * Template admin pour la vue détaillée d'une caution
 * Version 2.1.4 - Interface complète de gestion des cautions Stripe
 *}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-credit-card"></i>
        Caution #{$deposit.id_deposit} - {$deposit.booking_reference}
        <span class="badge pull-right {if $deposit.status == 'authorized'}badge-info{elseif $deposit.status == 'captured'}badge-danger{elseif $deposit.status == 'released'}badge-success{elseif $deposit.status == 'failed'}badge-important{else}badge-warning{/if}">
            {if $deposit.status == 'pending'}En attente
            {elseif $deposit.status == 'authorized'}Autorisée
            {elseif $deposit.status == 'captured'}Capturée
            {elseif $deposit.status == 'released'}Libérée
            {elseif $deposit.status == 'refunded'}Remboursée
            {elseif $deposit.status == 'failed'}Échec
            {else}{$deposit.status}{/if}
        </span>
    </div>
</div>

<div class="row">
    {* Colonne principale - Détails de la caution *}
    <div class="col-lg-8">
        
        {* Informations générales *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-info-circle"></i> Informations générales
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <td><strong>ID Caution :</strong></td>
                                <td>#{$deposit.id_deposit}</td>
                            </tr>
                            <tr>
                                <td><strong>Référence réservation :</strong></td>
                                <td>
                                    <a href="{$current_url}&controller=AdminBookerAuthReserved&viewbooker_auth_reserved&id_reserved={$deposit.id_reservation}&token={$token}">
                                        {$deposit.booking_reference}
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Client :</strong></td>
                                <td>{$deposit.customer_firstname} {$deposit.customer_lastname}</td>
                            </tr>
                            <tr>
                                <td><strong>Email :</strong></td>
                                <td><a href="mailto:{$deposit.customer_email}">{$deposit.customer_email}</a></td>
                            </tr>
                            <tr>
                                <td><strong>Élément réservé :</strong></td>
                                <td>{$deposit.booker_name}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <td><strong>Date réservation :</strong></td>
                                <td>{$deposit.date_reserved|date_format:"%d/%m/%Y"}</td>
                            </tr>
                            <tr>
                                <td><strong>Montant total :</strong></td>
                                <td>{$deposit.total_price|string_format:"%.2f"}€</td>
                            </tr>
                            <tr>
                                <td><strong>Statut réservation :</strong></td>
                                <td>
                                    <span class="badge {if $deposit.reservation_status == 0}badge-warning{elseif $deposit.reservation_status == 1}badge-info{elseif $deposit.reservation_status == 3}badge-success{elseif $deposit.reservation_status == 2}badge-important{else}badge-default{/if}">
                                        {$statuses[$deposit.reservation_status]}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Date création :</strong></td>
                                <td>{$deposit.date_add|date_format:"%d/%m/%Y %H:%M"}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        {* Détails de la caution *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-money"></i> Détails de la caution
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <td><strong>Montant caution :</strong></td>
                                <td class="text-primary">
                                    <strong>{($deposit.deposit_amount / 100)|string_format:"%.2f"}€</strong>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Montant capturé :</strong></td>
                                <td class="text-danger">
                                    {($deposit.captured_amount / 100)|string_format:"%.2f"}€
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Montant remboursé :</strong></td>
                                <td class="text-success">
                                    {($deposit.refunded_amount / 100)|string_format:"%.2f"}€
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Setup Intent ID :</strong></td>
                                <td>
                                    {if $deposit.setup_intent_id}
                                        <code class="small">{$deposit.setup_intent_id}</code>
                                    {else}
                                        <em>Non défini</em>
                                    {/if}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table">
                            <tr>
                                <td><strong>Payment Method ID :</strong></td>
                                <td>
                                    {if $deposit.payment_method_id}
                                        <code class="small">{$deposit.payment_method_id}</code>
                                    {else}
                                        <em>Non défini</em>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Payment Intent ID :</strong></td>
                                <td>
                                    {if $deposit.payment_intent_id}
                                        <code class="small">{$deposit.payment_intent_id}</code>
                                    {else}
                                        <em>Non défini</em>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Transaction Stripe :</strong></td>
                                <td>
                                    {if $deposit.stripe_transaction_id}
                                        <code class="small">{$deposit.stripe_transaction_id}</code>
                                    {else}
                                        <em>Aucune</em>
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Dernière MAJ :</strong></td>
                                <td>{$deposit.date_upd|date_format:"%d/%m/%Y %H:%M"}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                {* Dates importantes *}
                {if $deposit.date_authorized || $deposit.date_captured || $deposit.date_released || $deposit.date_refunded}
                    <div class="alert alert-info">
                        <h4><i class="icon-clock-o"></i> Chronologie</h4>
                        <ul class="list-unstyled">
                            {if $deposit.date_authorized}
                                <li><strong>Autorisée :</strong> {$deposit.date_authorized|date_format:"%d/%m/%Y %H:%M"}</li>
                            {/if}
                            {if $deposit.date_captured}
                                <li><strong>Capturée :</strong> {$deposit.date_captured|date_format:"%d/%m/%Y %H:%M"}</li>
                            {/if}
                            {if $deposit.date_released}
                                <li><strong>Libérée :</strong> {$deposit.date_released|date_format:"%d/%m/%Y %H:%M"}</li>
                            {/if}
                            {if $deposit.date_refunded}
                                <li><strong>Remboursée :</strong> {$deposit.date_refunded|date_format:"%d/%m/%Y %H:%M"}</li>
                            {/if}
                        </ul>
                    </div>
                {/if}
                
                {* Message d'erreur si échec *}
                {if $deposit.failure_reason}
                    <div class="alert alert-danger">
                        <h4><i class="icon-exclamation-triangle"></i> Raison de l'échec</h4>
                        <p>{$deposit.failure_reason}</p>
                    </div>
                {/if}
            </div>
        </div>
        
        {* Historique des actions *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-history"></i> Historique des actions
            </div>
            <div class="panel-body">
                {if $history && count($history) > 0}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Ancien statut</th>
                                    <th>Nouveau statut</th>
                                    <th>Montant</th>
                                    <th>Employé</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                {foreach from=$history item=entry}
                                    <tr>
                                        <td>{$entry.date_add|date_format:"%d/%m/%Y %H:%M"}</td>
                                        <td>
                                            <span class="label {if $entry.action_type == 'authorized'}label-info{elseif $entry.action_type == 'captured'}label-danger{elseif $entry.action_type == 'released'}label-success{elseif $entry.action_type == 'failed'}label-important{else}label-default{/if}">
                                                {if $entry.action_type == 'created'}Créée
                                                {elseif $entry.action_type == 'authorized'}Autorisée
                                                {elseif $entry.action_type == 'captured'}Capturée
                                                {elseif $entry.action_type == 'released'}Libérée
                                                {elseif $entry.action_type == 'refunded'}Remboursée
                                                {elseif $entry.action_type == 'failed'}Échec
                                                {else}{$entry.action_type}{/if}
                                            </span>
                                        </td>
                                        <td>
                                            {if $entry.old_status}
                                                <small class="text-muted">{$entry.old_status}</small>
                                            {else}
                                                -
                                            {/if}
                                        </td>
                                        <td>
                                            <small>{$entry.new_status}</small>
                                        </td>
                                        <td>
                                            {if $entry.amount}
                                                {($entry.amount / 100)|string_format:"%.2f"}€
                                            {else}
                                                -
                                            {/if}
                                        </td>
                                        <td>
                                            {if $entry.firstname && $entry.lastname}
                                                {$entry.firstname} {$entry.lastname}
                                            {else}
                                                <em>Automatique</em>
                                            {/if}
                                        </td>
                                        <td>
                                            {if $entry.details}
                                                <small class="text-muted">{$entry.details|truncate:50}</small>
                                            {else}
                                                -
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                {else}
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i> Aucun historique disponible.
                    </div>
                {/if}
            </div>
        </div>
    </div>
    
    {* Colonne sidebar - Actions *}
    <div class="col-lg-4">
        
        {* Actions rapides *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-cogs"></i> Actions
            </div>
            <div class="panel-body">
                
                {* Actions selon le statut *}
                {if $deposit.status == 'pending'}
                    <a href="{$current_url}&authorize_deposit&id_reservation={$deposit.id_reservation}&token={$token}" 
                       class="btn btn-primary btn-block"
                       onclick="return confirm('Êtes-vous sûr de vouloir autoriser cette caution ?')">
                        <i class="icon-credit-card"></i> Autoriser la caution
                    </a>
                {elseif $deposit.status == 'authorized'}
                    <a href="{$current_url}&capture_deposit&id_reservation={$deposit.id_reservation}&token={$token}" 
                       class="btn btn-danger btn-block"
                       onclick="return confirm('Êtes-vous sûr de vouloir capturer cette caution ?')">
                        <i class="icon-money"></i> Capturer la caution
                    </a>
                    <a href="{$current_url}&release_deposit&id_reservation={$deposit.id_reservation}&token={$token}" 
                       class="btn btn-success btn-block"
                       onclick="return confirm('Êtes-vous sûr de vouloir libérer cette caution ?')">
                        <i class="icon-unlock"></i> Libérer la caution
                    </a>
                {elseif $deposit.status == 'captured'}
                    <button type="button" class="btn btn-warning btn-block" data-toggle="modal" data-target="#refund-modal">
                        <i class="icon-undo"></i> Rembourser
                    </button>
                {/if}
                
                <hr>
                
                {* Actions générales *}
                <a href="{$current_url}&token={$token}" class="btn btn-default btn-block">
                    <i class="icon-list"></i> Retour à la liste
                </a>
                
                <a href="{$current_url}&controller=AdminBookerAuthReserved&viewbooker_auth_reserved&id_reserved={$deposit.id_reservation}&token={$token}" 
                   class="btn btn-info btn-block">
                    <i class="icon-eye"></i> Voir la réservation
                </a>
                
                <hr>
                
                {* Liens Stripe Dashboard *}
                {if $deposit.setup_intent_id}
                    <a href="https://dashboard.stripe.com/setup_intents/{$deposit.setup_intent_id}" 
                       target="_blank" class="btn btn-default btn-block">
                        <i class="icon-external-link"></i> Setup Intent Stripe
                    </a>
                {/if}
                
                {if $deposit.payment_intent_id}
                    <a href="https://dashboard.stripe.com/payments/{$deposit.payment_intent_id}" 
                       target="_blank" class="btn btn-default btn-block">
                        <i class="icon-external-link"></i> Payment Intent Stripe
                    </a>
                {/if}
            </div>
        </div>
        
        {* Métadonnées Stripe *}
        {if $deposit.metadata}
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-code"></i> Métadonnées Stripe
                </div>
                <div class="panel-body">
                    <pre class="small">{$deposit.metadata}</pre>
                </div>
            </div>
        {/if}
        
        {* Informations de debug *}
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-bug"></i> Informations techniques
            </div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <tr>
                        <td><small>ID BDD :</small></td>
                        <td><code>{$deposit.id_deposit}</code></td>
                    </tr>
                    <tr>
                        <td><small>ID Réservation :</small></td>
                        <td><code>{$deposit.id_reservation}</code></td>
                    </tr>
                    <tr>
                        <td><small>Créée le :</small></td>
                        <td><small>{$deposit.date_add}</small></td>
                    </tr>
                    <tr>
                        <td><small>Modifiée le :</small></td>
                        <td><small>{$deposit.date_upd}</small></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

{* Modal de remboursement *}
<div class="modal fade" id="refund-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="{$current_url}">
                <input type="hidden" name="token" value="{$token}">
                <input type="hidden" name="refund_deposit" value="1">
                <input type="hidden" name="id_reservation" value="{$deposit.id_reservation}">
                
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <i class="icon-undo"></i> Rembourser la caution
                    </h4>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="icon-warning"></i>
                        Cette action créera un remboursement sur Stripe et ne peut pas être annulée.
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_amount">Montant à rembourser (€)</label>
                        <input type="number" 
                               id="refund_amount" 
                               name="refund_amount" 
                               class="form-control" 
                               step="0.01" 
                               min="0.01" 
                               max="{($deposit.captured_amount / 100)|string_format:"%.2f"}"
                               value="{($deposit.captured_amount / 100)|string_format:"%.2f"}"
                               placeholder="Montant en euros">
                        <p class="help-block">
                            Montant maximum : {($deposit.captured_amount / 100)|string_format:"%.2f"}€
                            <br>Laissez vide pour rembourser l'intégralité.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_reason">Raison du remboursement</label>
                        <select id="refund_reason" name="refund_reason" class="form-control">
                            <option value="">-- Sélectionner une raison --</option>
                            <option value="requested_by_customer">Demandé par le client</option>
                            <option value="duplicate">Doublon</option>
                            <option value="fraudulent">Frauduleux</option>
                            <option value="subscription_canceled">Réservation annulée</option>
                            <option value="product_unsatisfactory">Service insatisfaisant</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="refund_notes">Notes internes (optionnel)</label>
                        <textarea id="refund_notes" 
                                  name="refund_notes" 
                                  class="form-control" 
                                  rows="3"
                                  placeholder="Notes pour l'historique..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="icon-undo"></i> Confirmer le remboursement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{* Modal de capture partielle *}
<div class="modal fade" id="capture-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="{$current_url}">
                <input type="hidden" name="token" value="{$token}">
                <input type="hidden" name="capture_deposit" value="1">
                <input type="hidden" name="id_reservation" value="{$deposit.id_reservation}">
                
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                    <h4 class="modal-title">
                        <i class="icon-money"></i> Capturer la caution
                    </h4>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="icon-info-circle"></i>
                        Cette action débitera la carte bancaire du client.
                    </div>
                    
                    <div class="form-group">
                        <label for="capture_amount">Montant à capturer (€)</label>
                        <input type="number" 
                               id="capture_amount" 
                               name="capture_amount" 
                               class="form-control" 
                               step="0.01" 
                               min="0.01" 
                               max="{($deposit.deposit_amount / 100)|string_format:"%.2f"}"
                               value="{($deposit.deposit_amount / 100)|string_format:"%.2f"}"
                               placeholder="Montant en euros">
                        <p class="help-block">
                            Montant maximum : {($deposit.deposit_amount / 100)|string_format:"%.2f"}€
                            <br>Laissez vide pour capturer l'intégralité.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label for="capture_reason">Raison de la capture</label>
                        <textarea id="capture_reason" 
                                  name="capture_reason" 
                                  class="form-control" 
                                  rows="3" 
                                  required
                                  placeholder="Expliquez pourquoi cette caution est capturée..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="icon-money"></i> Confirmer la capture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Confirmation pour les actions sensibles
    $('.btn-danger, .btn-warning').click(function(e) {
        if ($(this).data('toggle') !== 'modal') {
            var action = $(this).text().toLowerCase();
            if (!confirm('Êtes-vous sûr de vouloir ' + action + ' cette caution ?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Auto-refresh toutes les 30 secondes si en cours de traitement
    {if $deposit.status == 'pending'}
        setTimeout(function() {
            location.reload();
        }, 30000);
    {/if}
});
</script>

<style>
.table code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 85%;
    word-break: break-all;
}

.panel-heading .badge {
    font-size: 11px;
    padding: 4px 8px;
}

.modal-body .alert {
    margin-bottom: 20px;
}

.help-block {
    margin-top: 5px;
    font-size: 12px;
    color: #737373;
}

pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px;
    font-size: 11px;
    max-height: 200px;
    overflow-y: auto;
}

.label {
    display: inline-block;
    margin-bottom: 2px;
}

.btn-block {
    margin-bottom: 5px;
}

.table-condensed td {
    padding: 4px 8px;
    font-size: 12px;
}
</style>
