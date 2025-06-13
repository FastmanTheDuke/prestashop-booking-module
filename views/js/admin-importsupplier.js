 /* 08/2106
 * beforebigbang/La Teapot du web pour le vieux plongeur
 * Import version 32 : on a désormais la possibilité de créer des produits;
 * ceci associe aussi aux boutiques les produits
 */
 
$(document).ready(function() {
    import_controller_link = $('#import_controller_link').val();
    current_import_session = {'start' : "1"};
    current_import_stopprocess = false;
    
    $('#configuration_form').submit(
        function()
        {
            current_import_stopprocess = false;
            current_import_session = {'start' : "1"};
            $( "#import-process-result" ).val('');
            //a remettre $('#configuration_form_submit_btn').hide();
            $('#import-process .panel-heading').html('<i class="icon-spinner"></i> Traitement en cours');
            
            $('#import-process').show();
            ajaxProcessImportSupplierFiles();
            return false;
        }
    );
    
    $('#import-process-stop').click(function(){current_import_stopprocess = true;});
    
    function ajaxProcessImportSupplierFiles()
    {

        var data = $('#configuration_form').serialize();

        //console.log($.param(current_import_session));
        $.ajax({
            type: "POST",
            url: import_controller_link + "&" + $.param(current_import_session),
            data: data,
            success: function( data ){
                
                //console.log(data);
                $( "#import-process-result" ).val($( "#import-process-result" ).val() + data.message);
               
                var textarea = document.getElementById('import-process-result');
                textarea.scrollTop = textarea.scrollHeight;
                
                if (!current_import_stopprocess && data.next_step)
                {
                    current_import_session = data.current_import_session;
                    ajaxProcessImportSupplierFiles()
                }
                else
                {
                    $('#configuration_form_submit_btn').show();
                    $('#import-process .panel-heading').html('<i class="icon-smile-o"></i> Fini !');
                }
            },
            dataType: 'json'
        });
    }
    
});