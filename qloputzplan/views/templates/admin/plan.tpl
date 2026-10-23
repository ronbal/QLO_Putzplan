<link rel="stylesheet" href="{$css_url|escape:'html':'UTF-8'}">
<div class="panel qpp-noprint">
  <div class="qpp-toolbar">
    <a class="btn btn-default" href="{$prev_url|escape:'html':'UTF-8'}">&laquo; Vorwoche</a>
    <a class="btn btn-default" href="{$today_url|escape:'html':'UTF-8'}">Diese Woche</a>
    <a class="btn btn-default" href="{$next_url|escape:'html':'UTF-8'}">Naechste Woche &raquo;</a>
    <select class="form-control qpp-hotel" onchange="window.location.href=this.value;">{foreach from=$hotel_urls item=h}<option value="{$h.url|escape:'html':'UTF-8'}"{if $h.selected} selected{/if}>{$h.name|escape:'html':'UTF-8'}</option>{/foreach}</select>
    <button type="button" class="btn btn-primary" onclick="window.print();">Drucken / als PDF speichern</button>
  </div>
  <p class="help-block">Teilen-Link (nur lesen, ohne Login; fuer Foto oder Weiterleiten):</p>
  <input type="text" class="form-control" readonly value="{$share_url|escape:'html':'UTF-8'}" onclick="this.select();">
</div>

{include file=$plan_tpl}
