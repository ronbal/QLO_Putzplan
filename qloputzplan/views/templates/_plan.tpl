{* Gemeinsame Wochenliste fuer Admin und Teilen-Ansicht. *}
<div class="qpp-sheet">
  <h1 class="qpp-title">Anreise- &amp; Vorbereitungsliste <span class="qpp-week">{$week_title|escape:'html':'UTF-8'}{if $hotel_title} &middot; {$hotel_title|escape:'html':'UTF-8'}{/if}</span></h1>

  {assign var=anyItems value=false}
  {foreach from=$days item=day}
    {if $day.items|count}
      {assign var=anyItems value=true}
      <h2 class="qpp-day">{$day.label|escape:'html':'UTF-8'}</h2>
      {foreach from=$day.items item=item}
        <div class="qpp-row{if $item.done} qpp-done{/if}" id="r{$item.task_room|intval}">
          <div class="qpp-left">
            {if $item.reminder}<div class="qpp-reminder">{$item.reminder|escape:'html':'UTF-8'}</div>{/if}
            <div class="qpp-unit">{$item.unit|escape:'html':'UTF-8'} {if $item.task_label}&middot; {$item.task_label|escape:'html':'UTF-8'}{else}putzen{/if}</div>
            <div class="qpp-sub">{$item.departure_info|escape:'html':'UTF-8'}</div>
            {if $item.has_next}
              <div class="qpp-prep">{$item.prep_title|escape:'html':'UTF-8'}</div>
              <div class="qpp-persons">{$item.persons_text|escape:'html':'UTF-8'}</div>
              {if $item.services_text}<div class="qpp-sub">{$item.services_text|escape:'html':'UTF-8'}</div>{/if}
            {/if}
            {if $item.checklist|count}
              <ul class="qpp-check">
                {foreach from=$item.checklist item=c}
                  <li><span class="qpp-box">&#9744;</span> {$c|escape:'html':'UTF-8'}</li>
                {/foreach}
              </ul>
            {/if}
          </div>
          <div class="qpp-right">
            <div class="qpp-next">{$item.next_info|escape:'html':'UTF-8'}</div>
            {if $item.gap_hint}<div class="qpp-gap">{$item.gap_hint|escape:'html':'UTF-8'}</div>{/if}
            {if $item.done}<div class="qpp-status">&#10003; {$item.done_text|escape:'html':'UTF-8'}</div>{/if}
            {if isset($item.app_code)}
              <div class="qpp-app">Code Schl&uuml;sselbox: <strong>{if $item.app_code}{$item.app_code|escape:'html':'UTF-8'}{else}&ndash;{/if}</strong></div>
              {if $item.app_reports|count}
                <div class="qpp-app-reports">
                  {foreach from=$item.app_reports item=rep}
                    <div><strong>{$rep.category|escape:'html':'UTF-8'}</strong> &middot; {$rep.status|escape:'html':'UTF-8'} &middot; {$rep.date|escape:'html':'UTF-8'}{if $rep.photos} &middot; {$rep.photos|intval} Foto{if $rep.photos > 1}s{/if}{/if}{if $rep.message}<br>{$rep.message|escape:'html':'UTF-8'}{/if}</div>
                  {/foreach}
                  <a href="{$item.app_reports_url|escape:'html':'UTF-8'}" class="qpp-noprint">Meldungen ansehen &raquo;</a>
                </div>
              {/if}
            {/if}
            {if $can_tick}
              <form method="post" action="{$tick_url|escape:'html':'UTF-8'}" class="qpp-noprint qpp-tickform">
                <input type="hidden" name="qpp_room" value="{$item.task_room|intval}">
                <input type="hidden" name="qpp_date" value="{$item.task_date|escape:'html':'UTF-8'}">
                <input type="hidden" name="qpp_type" value="{$item.task_type|escape:'html':'UTF-8'}">
                {if $item.done}
                  <button type="submit" name="qpp_action" value="undo" class="qpp-btn qpp-undo">R&uuml;ckg&auml;ngig</button>
                {else}
                  <button type="submit" name="qpp_action" value="done" class="qpp-btn qpp-tick">{if $item.task_type == 'check'}Kontrolle erledigt{else}Gereinigt{/if}</button>
                {/if}
              </form>
            {/if}
          </div>
        </div>
      {/foreach}
    {/if}
  {/foreach}
  {if !$anyItems}
    <p class="qpp-empty">In dieser Woche gibt es keine Abreisen.</p>
  {/if}
</div>
