<style>

{literal}
/* FONTS */
@font-face {
    font-family: 'hando_softblack';
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.eot');
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.eot?#iefix') format('embedded-opentype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.woff2') format('woff2'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.woff') format('woff'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.ttf') format('truetype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-black-webfont.svg#hando_softblack') format('svg');
    font-weight: normal;
    font-style: normal;
	font-display: swap;
}
@font-face {
    font-family: 'hando_softbold';
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.eot');
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.eot?#iefix') format('embedded-opentype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.woff2') format('woff2'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.woff') format('woff'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.ttf') format('truetype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-bold-webfont.svg#hando_softbold') format('svg');
    font-weight: normal;
    font-style: normal;
	font-display: swap;
}
@font-face {
    font-family: 'hando_softlight';
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.eot');
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.eot?#iefix') format('embedded-opentype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.woff2') format('woff2'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.woff') format('woff'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.ttf') format('truetype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-light-webfont.svg#hando_softlight') format('svg');
    font-weight: normal;
    font-style: normal;
	font-display: swap;
}
@font-face {
    font-family: 'hando_softregular';
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.eot');
    src: url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.eot?#iefix') format('embedded-opentype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.woff2') format('woff2'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.woff') format('woff'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.ttf') format('truetype'),
         url('../../../../../themes/happesmoke/assets/fonts/handsoft/handosoft-regular-webfont.svg#hando_softregular') format('svg');
    font-weight: normal;
    font-style: normal;
	font-display: swap;
}
.flexbox-container {
  display: flex;
  justify-content: space-around;
  align-items: center;
  align-content: space-around;
}
.flexbox-container-vertical {
  display: flex;
  flex-direction: column;
  justify-content: space-around;
  align-items: center;
  align-content: center;
}
.hours,.days_hours{min-height:100vh;}
.next,.prev {
    font-family: Fontawesome,hando_softblack, sans-serif;
    font-size: 2rem;
    margin-left: 30px;
    cursor: pointer;
}

.y h3 {
    margin: 0!important;padding:1rem;
}

.y {
    text-align: center;
    display: flex;
    justify-content: center;
    align-items: center;
    align-content: center;
}
.prev {
    margin: 0 30px 0;
}
{/literal}
</style>
<script type="text/javascript">
	AJAXURL="{$ajaxUrl}";
	//$(function () {
	$("document").ready(function(){
		$(".next").click(function () {
			//var ids = $(this).val();
			var datenum = $(this).data("datenum");
			var dir = $(this).data("dir");
			var action = $(this).data("action");		
			$.ajax({
				dataType: "JSON",
				type: "POST",				
				url: AJAXURL,
				data: { 
				  ajax: true, 
				  action: action,
				  dir: dir,
				  datenum: datenum,
				}, 
				success: function (result) {
					console.log(result);
					$(".main").html(result);
					$("html,body").stop().animate({  
						scrollTop:$(".main").position().top
						//scrollTop:0
					}, 300); 
				},
				error: function (jqXHR, textStatus, errorThrown) {
					var errorMsg = textStatus + ': ' + errorThrown;
					console.log(AJAXURL + action+"&dir="+dir+"&id="+ids);
					console.log("BAD");
				}
			});
			return false;
		});	
	});

</script>
</div>