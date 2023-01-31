<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xsl:stylesheet SYSTEM "ulang://common">
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

	<xsl:template match="data[@type = 'form' and (@action = 'modify' or @action = 'create')]">
		<xsl:if test="./id">
			<div class="editing-functions-wrapper">
				<div class="tabs editing"></div>
				<div class="toolbar clearfix">
					<a id="remove-object" title="&label-delete;" class="icon-action">
						<i class="small-ico i-remove"></i>
					</a>
					<script>
						var del_func_name = 'del';
						var obj_id = '<xsl:value-of select="./id" />';

						$(document).ready(function (){
							$('#remove-object').on('click',function (){
								var csrf = window.parent.csrfProtection.token;
								openDialog('', getLabel('js-del-redirect-title-short'), {
									cancelButton: true,
									html: getLabel('js-del-redirect-sure'),
									confirmText: getLabel('js-delete'),
									cancelText: getLabel('js-cancel'),
									confirmCallback: function(popupName) {
										$.ajax({
											url:'/admin/'+curent_module+'/'+del_func_name+'.xml?childs=1&amp;element='+obj_id+'&amp;allow=true&amp;csrf=' + csrf,
											dataType:'xml',
											success: function(data){
												closeDialog(popupName);
												window.location = '/admin/'+curent_module+'/';
											}
										});
									}
								});
							});
						});
					</script>
				</div>
			</div>
		</xsl:if>
		<div class="tabs-content module {$module}-module">
			<div class="section selected">
				<xsl:apply-templates select="$errors" />
				<form class="form_modify"  method="post" action="do/" enctype="multipart/form-data">
					<input type="hidden" name="referer" value="{/result/@referer-uri}" id="form-referer"/>
					<input type="hidden" name="domain" value="{$domain-floated}"/>
					<input type="hidden" name="permissions-sent" value="1"/>
					<script type="text/javascript">
						var treeLink = function(key, value){
							var settings = SettingsStore.getInstance();
							return settings.set(key, value, 'expanded');
						}
					</script>
					<div class="panel-settings">
						<div class="title">
							<h3>
								<xsl:text>&label-redirect-params;</xsl:text>
							</h3>
						</div>
						<div class="content">
							<div class="layout">
								<div class="column">
									<div class="row">
										<div class="col-md-6 default-empty-validation">
											<div class="title-edit">
												<acronym title="{@tip}">
													<xsl:text>&label-field-level-1;</xsl:text>
												</acronym>
												<sup><xsl:text>*</xsl:text></sup>
											</div>
											<span>
												<input class="default" type="text" name="{concat(./field_name_prefix, '[level_1]')}" value="{./level_1}" id="{generate-id()}"/>
											</span>
										</div>
										<div class="col-md-6 default-empty-validation">
											<div class="title-edit">
												<acronym title="{@tip}">
													<xsl:text>&label-field-level-2;</xsl:text>
												</acronym>
												<sup><xsl:text>*</xsl:text></sup>
											</div>
											<span>
												<input class="default" type="text" name="{concat(./field_name_prefix, '[level_2]')}" value="{./level_2}" id="{generate-id()}"/>
											</span>
										</div>
									</div>
								</div>
								<div class="column">
									<div  class="infoblock">
										<h3>
											<xsl:text>&type-edit-tip;</xsl:text>
										</h3>
										<div class="content" >
										</div>
										<div class="group-tip-hide"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<xsl:choose>
							<xsl:when test="$data-action = 'create'">
								<xsl:call-template name="std-form-buttons-add"/>
							</xsl:when>
							<xsl:otherwise>
								<xsl:call-template name="std-form-buttons"/>
							</xsl:otherwise>
						</xsl:choose>
					</div>
				</form>
				<script type="text/javascript">
					var method = '<xsl:value-of select="/result/@method"/>';
					$('.form_modify').find('.select').each(function(){
						var current = $(this);
						buildSelect(current);
					});
				</script>
			</div>
		</div>

		<xsl:call-template name="error-checker" >
			<xsl:with-param name="launch" select="1" />
		</xsl:call-template>
	</xsl:template>

</xsl:stylesheet>