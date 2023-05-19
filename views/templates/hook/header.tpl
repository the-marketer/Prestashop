{*
*  theMarketer module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}

{if isset($tm_id) && $tm_id}
	<script> 
		var tmpagename = "{$tm_page_name|cleanHtml nofilter}";
	</script>
    <!-- Themarketer Tracking -->
	<script>
      window.mktr = window.mktr || {};
      window.mktr.siteurl = "{Context::getContext()->shop->getBaseURL(true)|cleanHtml nofilter}";
      window.mktr.siteurl = window.mktr.siteurl.substr(window.mktr.siteurl.length - 1) === "/" ? window.mktr.siteurl : window.mktr.siteurl+"/";
      
      (function(){
      var mktr = document.createElement("script"); mktr.async = true; mktr.src = "https://t.themarketer.com/t/j/{$tm_id|cleanHtml nofilter}";
      var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(mktr,s);})();
	</script>
    <!-- End Themarketer Tracking -->
    {if $tm_google_status && !empty($tm_google_key)}
    <!-- Google Tag Manager -->
    <script>
    (function(w,d,s,l,i){
        {literal}w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);{/literal}
    })(window,document,'script','dataLayer','{$tm_google_key|cleanHtml nofilter}');
    </script>
    <!-- End Google Tag Manager -->
    {/if}
{/if}
