<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Putzplan {$week_title|escape:'html':'UTF-8'}</title>
  <link rel="stylesheet" href="{$css_url|escape:'html':'UTF-8'}">
</head>
<body class="qpp-body">
  <div class="qpp-toolbar qpp-noprint">
    <a class="qpp-btn" href="{$prev_url|escape:'html':'UTF-8'}">&laquo; Vorwoche</a>
    <a class="qpp-btn" href="{$today_url|escape:'html':'UTF-8'}">Diese Woche</a>
    <a class="qpp-btn" href="{$next_url|escape:'html':'UTF-8'}">Naechste Woche &raquo;</a>
    <button type="button" class="qpp-btn" onclick="window.print();">Drucken / PDF</button>
  </div>
  {include file=$plan_tpl}
</body>
</html>
