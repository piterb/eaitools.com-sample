$.fn.icdph = function() {
	
    /**
     * API URL
     * @type String
     */
    var baseUrl = 'https://api.eaitools.com';

	function organizationpreview(ul, item) {
        //var content = '<strong>' + item.name + '</strong> ('+item.id_number+')';
        var content = item.name + ' ('+item.id_number+')';
        if(item.city != null && item.city.length > 0){
            //content += '<br>';
            //content += item.city;
            content += ' - '+item.city;
        }
        
        return $( '<li>' ).append("<div class=\"ui-menu-item-wrapper\">"+content+"</div>").appendTo(ul); 
    }
	
	function organizationdetail(event, ui, field, accesstoken) {
		 var fieldname = field.attr('data-icdph-form');
         $(field).val(ui.item[fieldname]);
         $(field).addClass("ui-autocomplete-loading");
         $.ajax({
             url: baseUrl + '/icdph/organization/view',
             dataType: 'jsonp',
             data: {
                 id: ui.item.rpoid,
                 "access-token": accesstoken
             },
             success: function(data) {
                 if('name' in data){
                	 $(form).find('input[data-icdph-form=\"name\"]').val(data.name);
                     
                	 $(form).find('input[data-icdph-form=\"id_number\"]').val(data.id_number);
                     $(form).find('input[data-icdph-form=\"tax_number\"]').val(data.tax_number);
                     $(form).find('input[data-icdph-form=\"vat_number\"]').val(data.vat_number);
                     $(form).find('input[data-icdph-form=\"address\"]').val(data.address);
                     if(data.address)
                     	$(form).find('input[data-icdph-form=\"address_full\"]').val(data.address+', '+data.postal_code+' '+data.city);
                     else
                     	$(form).find('input[data-icdph-form=\"address_full\"]').val(data.postal_code+' '+data.city);
                     $(form).find('input[data-icdph-form=\"city\"]').val(data.city);
                     $(form).find('input[data-icdph-form=\"postal_code\"]').val(data.postal_code);
                     $(form).find('input[data-icdph-form=\"registry_name\"]').val(data.registry_name);
                     $(form).find('input[data-icdph-form=\"registry_id\"]').val(data.registry_id);
                     $(form).find('input[data-icdph-form=\"registry_full\"]').val(data.registry_name + ', ' + data.registry_id);
                 }
             }
         }).always(function(){ $(field).removeClass("ui-autocomplete-loading"); });
         event.preventDefault();
     }

    var match = $('script[src*="icdph"]').attr('src').match(/access-token=([A-Za-z0-9\-]+)/);
    if(match != null && match.length >= 2 && match[0].indexOf("access-token") == 0){
        var accesstoken = match[1];
    } else {
        console.error("Script must be called with access-token query parameter.");
    }
    
    var form = this;
    $(form).addClass("icdph-form");
    var name = $(this).find('input[data-icdph-form=\"name\"]');
    var id_number = $(this).find('input[data-icdph-form=\"id_number\"]');
    
    // Autocomplete init for name
    $(name).autocomplete({
        source: function(request, response){
            $.ajax({
                url: baseUrl + '/icdph/organization/search',
                dataType: 'jsonp',
                data: {
                    term: request.term,
                    "access-token": accesstoken
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 3,
        focus: function( event, ui ) {
            $(name).val(ui.item.name);
            return false;  
        },
        select: function(event, ui) { return organizationdetail(event, ui, name, accesstoken); }
    }).autocomplete('instance')._renderItem = function(ul, item) { return organizationpreview(ul, item); };
    
    // Autocomplete init for ico
    $(id_number).autocomplete({
        source: function(request, response){
            $.ajax({
                url: baseUrl + '/icdph/organization/search-ico',
                dataType: 'jsonp',
                data: {
                    ico: request.term,
                    "access-token": accesstoken
                },
                success: function(data) {
                    response(data);
                }
            });
        },
        minLength: 8,
        focus: function( event, ui ) {
            $(id_number).val(ui.item.id_number);
            return false;  
        },
        select: function(event, ui) { return organizationdetail(event, ui, id_number, accesstoken); }
    }).autocomplete('instance')._renderItem = function(ul, item) { return organizationpreview(ul, item); };
    
};