{*
* Template AJAX général pour les contrôleurs administrateurs
* Gestion des requêtes AJAX et des interactions dynamiques
*}

<script>
$(document).ready(function() {
    
    // Configuration globale AJAX
    $.ajaxSetup({
        beforeSend: function() {
            // Afficher un indicateur de chargement global
            showGlobalLoading(true);
        },
        complete: function() {
            // Masquer l'indicateur de chargement
            showGlobalLoading(false);
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX:', error);
            showNotification('Erreur de communication avec le serveur', 'error');
        }
    });
    
    // Gestionnaire pour les liens AJAX
    $(document).on('click', '.ajax-link', function(e) {
        e.preventDefault();
        
        const url = $(this).attr('href');
        const method = $(this).data('method') || 'GET';
        const confirm_message = $(this).data('confirm');
        
        if (confirm_message && !confirm(confirm_message)) {
            return;
        }
        
        $.ajax({
            url: url,
            type: method,
            dataType: 'json',
            success: function(response) {
                handleAjaxResponse(response);
            }
        });
    });
    
    // Gestionnaire pour les formulaires AJAX
    $(document).on('submit', '.ajax-form', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const url = form.attr('action') || window.location.href;
        const method = form.attr('method') || 'POST';
        const formData = new FormData(this);
        
        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                handleAjaxResponse(response, form);
            }
        });
    });
    
    // Gestionnaire pour les actions rapides
    $(document).on('click', '.quick-action', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const action = button.data('action');
        const id = button.data('id');
        const confirmMsg = button.data('confirm') || 'Êtes-vous sûr ?';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Désactiver le bouton pendant la requête
        button.prop('disabled', true);
        const originalText = button.html();
        button.html('<i class="icon-spinner icon-spin"></i>');
        
        $.ajax({
            url: '{$ajaxUrl|default:""}&action=' + action,
            type: 'POST',
            data: {
                id: id,
                ajax: 1,
                action: action
            },
            dataType: 'json',
            success: function(response) {
                handleAjaxResponse(response);
                
                // Actions spécifiques selon le type
                if (response.success) {
                    switch (action) {
                        case 'delete':
                            button.closest('tr').fadeOut();
                            break;
                        case 'toggle_status':
                            button.toggleClass('btn-success btn-danger');
                            break;
                    }
                }
            },
            complete: function() {
                // Restaurer le bouton
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Actualisation automatique des listes
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        const refreshRate = parseInt('{$auto_refresh_rate|default:0}');
        
        if (refreshRate > 0) {
            autoRefreshInterval = setInterval(function() {
                refreshCurrentList();
            }, refreshRate * 1000);
        }
    }
    
    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    }
    
    function refreshCurrentList() {
        const listContainer = $('.list-container, .panel-body').first();
        
        if (listContainer.length) {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: { ajax_refresh: 1 },
                success: function(response) {
                    if (response && response.list_content) {
                        listContainer.html(response.list_content);
                        showNotification('Liste actualisée', 'info');
                    }
                },
                error: function() {
                    // Ignorer les erreurs d'actualisation automatique
                }
            });
        }
    }
    
    // Gestion des réponses AJAX standardisées
    function handleAjaxResponse(response, form = null) {
        if (response.success) {
            if (response.message) {
                showNotification(response.message, 'success');
            }
            
            // Actions post-succès
            if (response.redirect) {
                setTimeout(function() {
                    window.location.href = response.redirect;
                }, 1500);
            } else if (response.reload) {
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else if (response.update_element && response.new_content) {
                $(response.update_element).html(response.new_content);
            }
            
            // Réinitialiser le formulaire si nécessaire
            if (form && response.reset_form) {
                form[0].reset();
            }
            
        } else {
            const message = response.message || 'Une erreur est survenue';
            showNotification(message, 'error');
            
            // Afficher les erreurs de validation
            if (response.errors && form) {
                displayFormErrors(form, response.errors);
            }
        }
        
        // Mettre à jour les statistiques si fournies
        if (response.stats) {
            updateStats(response.stats);
        }
        
        // Déclencher un événement personnalisé
        $(document).trigger('ajaxResponseHandled', [response]);
    }
    
    // Affichage des erreurs de validation
    function displayFormErrors(form, errors) {
        // Effacer les erreurs précédentes
        form.find('.has-error').removeClass('has-error');
        form.find('.error-message').remove();
        
        // Afficher les nouvelles erreurs
        $.each(errors, function(field, message) {
            const fieldElement = form.find('[name="' + field + '"]');
            const formGroup = fieldElement.closest('.form-group');
            
            formGroup.addClass('has-error');
            formGroup.append('<div class="error-message text-danger small">' + message + '</div>');
        });
    }
    
    // Mettre à jour les statistiques
    function updateStats(stats) {
        $.each(stats, function(key, value) {
            $('.stat-' + key).text(value);
        });
    }
    
    // Afficher/masquer le chargement global
    function showGlobalLoading(show) {
        if (show) {
            if (!$('#global-loading').length) {
                $('body').append('<div id="global-loading" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.1);z-index:9999;display:flex;align-items:center;justify-content:center;"><div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 8px rgba(0,0,0,0.1);"><i class="icon-spinner icon-spin"></i> Chargement...</div></div>');
            }
        } else {
            $('#global-loading').remove();
        }
    }
    
    // Système de notifications
    function showNotification(message, type = 'info') {
        // Utiliser le système de notification de PrestaShop si disponible
        if (typeof $.growl === 'function') {
            const options = {
                type: type,
                allow_dismiss: true,
                delay: type === 'error' ? 0 : 4000,
                animate: {
                    enter: 'animated fadeInRight',
                    exit: 'animated fadeOutRight'
                }
            };
            
            $.growl({
                message: message,
                title: getNotificationTitle(type)
            }, options);
            
        } else {
            // Fallback avec alert natif
            alert(getNotificationTitle(type) + ': ' + message);
        }
    }
    
    function getNotificationTitle(type) {
        const titles = {
            'success': 'Succès',
            'error': 'Erreur',
            'warning': 'Attention',
            'info': 'Information'
        };
        
        return titles[type] || 'Notification';
    }
    
    // Gestion des tableaux responsifs
    function makeTablesResponsive() {
        $('.table').each(function() {
            if (!$(this).parent().hasClass('table-responsive')) {
                $(this).wrap('<div class="table-responsive"></div>');
            }
        });
    }
    
    // Gestion des tooltips dynamiques
    function initDynamicTooltips() {
        $(document).on('mouseenter', '[data-toggle="tooltip"]', function() {
            $(this).tooltip('show');
        });
    }
    
    // Sauvegarde automatique des formulaires
    function initAutoSave() {
        $('.auto-save-form').on('change', 'input, select, textarea', function() {
            const form = $(this).closest('form');
            const field = $(this).attr('name');
            const value = $(this).val();
            
            // Sauvegarder individuellement le champ
            $.ajax({
                url: form.attr('action') || window.location.href,
                type: 'POST',
                data: {
                    auto_save: 1,
                    field: field,
                    value: value,
                    id: form.find('input[name$="_id"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Afficher un indicateur de sauvegarde
                        showSaveIndicator($(this), true);
                    } else {
                        showSaveIndicator($(this), false);
                    }
                }.bind(this)
            });
        });
    }
    
    function showSaveIndicator(element, success) {
        const indicator = $('<span class="save-indicator"></span>');
        
        if (success) {
            indicator.html('<i class="icon-check text-success"></i>').addClass('success');
        } else {
            indicator.html('<i class="icon-times text-danger"></i>').addClass('error');
        }
        
        element.after(indicator);
        
        setTimeout(function() {
            indicator.fadeOut(function() {
                $(this).remove();
            });
        }, 2000);
    }
    
    // Gestion des raccourcis clavier
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl+S pour sauvegarder
            if (e.ctrlKey && e.which === 83) {
                e.preventDefault();
                const activeForm = $('form:visible').first();
                if (activeForm.length) {
                    activeForm.trigger('submit');
                }
            }
            
            // Echap pour fermer les modals
            if (e.which === 27) {
                $('.modal:visible').modal('hide');
            }
        });
    }
    
    // Initialisation
    makeTablesResponsive();
    initDynamicTooltips();
    initAutoSave();
    initKeyboardShortcuts();
    startAutoRefresh();
    
    // Nettoyage lors de la fermeture de la page
    $(window).on('beforeunload', function() {
        stopAutoRefresh();
    });
    
    // Exposition de fonctions utiles
    window.BookingAjax = {
        showNotification: showNotification,
        showGlobalLoading: showGlobalLoading,
        refreshCurrentList: refreshCurrentList,
        handleAjaxResponse: handleAjaxResponse
    };
});
</script>

{* CSS pour les éléments AJAX *}
<style>
.ajax-loading {
    opacity: 0.6;
    pointer-events: none;
}

.quick-action {
    margin: 0 2px;
}

.quick-action:disabled {
    opacity: 0.6;
}

.save-indicator {
    margin-left: 5px;
    animation: fadeIn 0.3s ease-in;
}

.save-indicator.success {
    color: #28a745;
}

.save-indicator.error {
    color: #dc3545;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.has-error {
    border-color: #dc3545 !important;
}

.error-message {
    margin-top: 5px;
    font-size: 12px;
}

.table-responsive {
    border: none;
}

.notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
}

.ajax-form .form-group {
    position: relative;
}

.auto-refresh-indicator {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    z-index: 1000;
}

.keyboard-shortcut-hint {
    font-size: 11px;
    color: #6c757d;
    margin-left: 5px;
}

/* Animations pour les actions rapides */
.quick-action-success {
    animation: actionSuccess 0.6s ease-out;
}

.quick-action-error {
    animation: actionError 0.6s ease-out;
}

@keyframes actionSuccess {
    0% { transform: scale(1); background-color: initial; }
    50% { transform: scale(1.1); background-color: #28a745; }
    100% { transform: scale(1); background-color: initial; }
}

@keyframes actionError {
    0% { transform: scale(1); background-color: initial; }
    50% { transform: scale(1.1); background-color: #dc3545; }
    100% { transform: scale(1); background-color: initial; }
}

/* Responsive improvements */
@media (max-width: 768px) {
    .quick-action {
        padding: 1px 3px;
        font-size: 11px;
    }
    
    .table-responsive {
        font-size: 12px;
    }
    
    .notification-container {
        left: 10px;
        right: 10px;
        top: 10px;
    }
}
</style>

{* Indicateur d'actualisation automatique *}
{if $auto_refresh_rate|default:0 > 0}
<div class="auto-refresh-indicator">
    <i class="icon-refresh"></i> Auto-actualisation active ({$auto_refresh_rate}s)
</div>
{/if}