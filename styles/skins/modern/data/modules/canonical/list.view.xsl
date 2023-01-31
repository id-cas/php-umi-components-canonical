<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xsl:stylesheet SYSTEM "ulang://common">
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="/result[@method = 'lists']/data[@type = 'list' and @action = 'view']">
		<script src="/styles/skins/modern/data/modules/canonical/removeAllRedirects.js?{$system-build}" />

		<div class="tabs-content module">
			<div class="section selected">
				<div class="location">
					<!--<div class="imgButtonWrapper loc-left" style="bottom:0px;">-->
						<!--<a id="removeAllRedirectsButton" class="btn color-blue loc-left">-->
							<!--&label-button-remove-all-canonicals;-->
						<!--</a>-->
					<!--</div>-->

					<xsl:call-template name="entities.help.button" />
				</div>
				<div class="layout">
					<div class="column">
						<div id="tableWrapper"></div>
						<script src="/styles/common/js/node_modules/underscore/underscore-min.js?{$system-build}" />
						<script src="/styles/common/js/backbone.compiled.min.js?{$system-build}" />
						<script src="/styles/skins/modern/design/js/dataView/app.min.js?{$system-build}" />
						<script>
							(function(){
								new umiDataController({
								container:'#tableWrapper',
								prefix:'/admin/canonical',
								module:'canonical',
								controlParam:'',
								dataProtocol: 'json',
								domain:'<xsl:value-of select="$domain-id"/>',
								lang:'<xsl:value-of select="$lang-id"/>',
								<xsl:if test="$domainsCount > 1">
									domainsList:<xsl:apply-templates select="$domains-list" mode="ndc_domain_list"/>,
								</xsl:if>
								configUrl:'/admin/canonical/flushDataConfig/.json',
								debug:true
								}).start();
							})()
						</script>
					</div>
					<div class="column">
						<xsl:call-template name="entities.help.content" />
					</div>
				</div>
			</div>

		</div>

	</xsl:template>

</xsl:stylesheet>
