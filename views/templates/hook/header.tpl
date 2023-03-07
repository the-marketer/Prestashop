{*
*  theMarketer V1.0.0 module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<!-- Google Tag Manager -->
{literal} 
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-KP7NPMB');</script>
{/literal}
<!-- End Google Tag Manager -->
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KP7NPMB"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
{if isset($tm_id) && $tm_id}
	<script> 
		var tmpagename = "{$tm_page_name|cleanHtml nofilter}";
	</script>
    <!-- Themarketer Tracking -->
	<script>
      (function(){
      var mktr = document.createElement("script"); mktr.async = true; mktr.src = "https://t.themarketer.com/t/j/{$tm_id|cleanHtml nofilter}";
      var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(mktr,s);})();
	</script>
    <!-- End Themarketer Tracking -->
{/if}