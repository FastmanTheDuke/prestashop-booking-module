{*
* Template pour les actions rapides sur les réservations
* Boutons d'actions en ligne et scripts AJAX
*}

{if $quick_actions_enabled}
<script>
$(document).ready(function() {
    
    // Gestionnaire pour les actions rapides
    $('.quick-action-btn').on('click', function(e) {
        e.preventDefault();
        
        const action = $(this).data('action');
        const id = $(this).data('id');
        const row = $(this).closest('tr');
        
        if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
            return;
        }
        
        // Afficher un indicateur de chargement
        $(this).prop('disabled', true).html('<i class="icon-spinner icon-spin"></i>');
        
        // Effectuer la requête AJAX
        $.ajax({
            url: '{$ajaxUrl}&action=quickAction',
            type: 'POST',
            data: {
                quick_action: action,
                id_reserved: id,
                ajax: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Afficher le message de succès
                    showSuccessMessage(response.message);
                    
                    // Mettre à jour la ligne si nécessaire
                    if (action === 'accept') {
                        row.find('.status-badge').html('<span class="label label-info">Acceptée</span>');
                        row.find('[data-action="accept"]').remove();
                    } else if (action === 'refuse') {
                        row.find('.status-badge').html('<span class="label label-danger">Refusée</span>');
                        row.find('[data-action="accept"], [data-action="refuse"]').remove();
                    } else if (action === 'toggle_active') {
                        const activeCell = row.find('.active-cell');
                        if (response.new_active) {
                            activeCell.html('<i class="icon-check text-success"></i>');
                        } else {
                            activeCell.html('<i class="icon-times text-danger"></i>');
                        }
                    }
                    
                    // Recharger la page après 2 secondes pour voir les changements
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    
                } else {
                    showErrorMessage(response.message || 'Erreur lors de l\'opération');
                }
            },
            error: function() {
                showErrorMessage('Erreur de communication avec le serveur');
            },
            complete: function() {
                // Restaurer le bouton
                $('.quick-action-btn').prop('disabled', false);
            }
        });
    });
    
    // Afficher les messages
    function showSuccessMessage(message) {
        if (typeof $.growl === 'function') {
            $.growl.notice({ message: message });
        } else {
            alert(message);
        }
    }
    
    function showErrorMessage(message) {
        if (typeof $.growl === 'function') {
            $.growl.error({ message: message });
        } else {
            alert('Erreur: ' + message);
        }
    }
    
    // Actions en lot améliorées
    $('#bulk_action_submit_btn').on('click', function(e) {
        const bulkAction = $('#bulk_action_select_all').val();
        const selectedIds = [];
        
        $('input[name="booker_auth_reservedBox[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Veuillez sélectionner au moins une réservation');
            e.preventDefault();
            return false;
        }
        
        if (bulkAction === 'accept' || bulkAction === 'refuse') {
            const actionText = bulkAction === 'accept' ? 'accepter' : 'refuser';
            if (!confirm(`Êtes-vous sûr de vouloir ${actionText} ${selectedIds.length} réservation(s) ?`)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Filtre rapide par statut
    $('.status-filter-btn').on('click', function(e) {
        e.preventDefault();
        
        const status = $(this).data('status');
        const currentUrl = new URL(window.location);
        
        if (status === 'all') {
            currentUrl.searchParams.delete('status_filter');
        } else {
            currentUrl.searchParams.set('status_filter', status);
        }
        
        window.location.href = currentUrl.toString();
    });
    
    // Auto-actualisation des statistiques
    {if $pending_count > 0}
    setInterval(function() {
        // Actualiser le compteur de réservations en attente
        $.ajax({
            url: '{$ajaxUrl}&action=getPendingCount',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.count !== undefined) {
                    $('.pending-count').text(response.count);
                    
                    // Mettre à jour le titre de la page si nécessaire
                    if (response.count > 0) {
                        document.title = `(${response.count}) Réservations - Administration`;
                    }
                }
            }
        });
    }, 30000); // Actualiser toutes les 30 secondes
    {/if}
});
</script>

{* CSS pour les actions rapides *}
<style>
.quick-actions {
    white-space: nowrap;
}

.quick-action-btn {
    margin: 0 2px;
    padding: 2px 6px;
    font-size: 11px;
    line-height: 1.2;
}

.status-badge .label {
    font-size: 10px;
    padding: 2px 6px;
}

.reservation-row:hover {
    background-color: #f8f9fa;
}

.reservation-row.urgent {
    border-left: 3px solid #dc3545;
}

.reservation-row.today {
    border-left: 3px solid #ffc107;
}

.active-cell {
    text-align: center;
    font-size: 16px;
}

.customer-info {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.booking-reference {
    font-family: monospace;
    font-weight: bold;
    color: #007bff;
}

.price-cell {
    font-weight: bold;
    color: #28a745;
}

.date-cell {
    white-space: nowrap;
}

.status-filter-buttons {
    margin-bottom: 15px;
}

.status-filter-btn {
    margin-right: 5px;
    margin-bottom: 5px;
}

.status-filter-btn.active {
    background-color: #007bff;
    color: white;
}

.pending-indicator {
    position: relative;
}

.pending-indicator::after {
    content: '';
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.table-responsive {
    overflow-x: auto;
}

.action-dropdown {
    position: relative;
    display: inline-block;
}

.action-dropdown-content {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    right: 0;
}

.action-dropdown-content a {
    color: black;
    padding: 8px 12px;
    text-decoration: none;
    display: block;
}

.action-dropdown-content a:hover {
    background-color: #f1f1f1;
}

.action-dropdown:hover .action-dropdown-content {
    display: block;
}

.stats-summary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stats-summary .stat-item {
    text-align: center;
    padding: 10px;
}

.stats-summary .stat-number {
    font-size: 2em;
    font-weight: bold;
    display: block;
}

.stats-summary .stat-label {
    font-size: 0.9em;
    opacity: 0.8;
}
</style>

{* Template pour les boutons d'actions en ligne dans les cellules du tableau *}
{literal}
<script type="text/html" id="quick-actions-template">
    <div class="quick-actions">
        <button type="button" class="btn btn-xs btn-success quick-action-btn" 
                data-action="accept" data-id="{{id}}" title="Accepter">
            <i class="icon-check"></i>
        </button>
        <button type="button" class="btn btn-xs btn-warning quick-action-btn" 
                data-action="refuse" data-id="{{id}}" title="Refuser">
            <i class="icon-times"></i>
        </button>
        <button type="button" class="btn btn-xs btn-info quick-action-btn" 
                data-action="toggle_active" data-id="{{id}}" title="Activer/Désactiver">
            <i class="icon-power-off"></i>
        </button>
    </div>
</script>
{/literal}

{* Filtres rapides par statut *}
<div class="status-filter-buttons">
    <h4>Filtres rapides :</h4>
    <button type="button" class="btn btn-default btn-sm status-filter-btn" data-status="all">
        Toutes les réservations
    </button>
    <button type="button" class="btn btn-warning btn-sm status-filter-btn pending-indicator" data-status="pending">
        En attente <span class="badge pending-count">{$pending_count|default:0}</span>
    </button>
    <button type="button" class="btn btn-info btn-sm status-filter-btn" data-status="accepted">
        Acceptées
    </button>
    <button type="button" class="btn btn-success btn-sm status-filter-btn" data-status="paid">
        Payées
    </button>
    <button type="button" class="btn btn-primary btn-sm status-filter-btn" data-status="today">
        Aujourd'hui
    </button>
    <button type="button" class="btn btn-secondary btn-sm status-filter-btn" data-status="tomorrow">
        Demain
    </button>
</div>

{* Aide contextuelle *}
<div class="alert alert-info">
    <h4><i class="icon-lightbulb-o"></i> Conseils d'utilisation :</h4>
    <ul>
        <li><strong>Actions rapides :</strong> Utilisez les boutons colorés pour accepter/refuser rapidement les réservations</li>
        <li><strong>Sélection multiple :</strong> Cochez plusieurs réservations et utilisez les actions en lot</li>
        <li><strong>Tri et filtres :</strong> Cliquez sur les en-têtes pour trier, utilisez les filtres rapides</li>
        <li><strong>Notifications :</strong> Le système vous alertera automatiquement des nouvelles réservations</li>
    </ul>
</div>

{/if}