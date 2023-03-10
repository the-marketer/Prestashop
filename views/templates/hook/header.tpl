{*
*  theMarketer V1.0.0 module   
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
      (function(){
      var mktr = document.createElement("script"); mktr.async = true; mktr.src = "https://t.themarketer.com/t/j/{$tm_id|cleanHtml nofilter}";
      var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(mktr,s);})();
	</script>
    <!-- End Themarketer Tracking -->
{/if}